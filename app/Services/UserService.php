<?php

namespace App\Services;

use App\Actions\SendSmsCodeAction;
use App\Models\User;
use App\Repositories\Interfaces\FavoriteRepositoryInterface;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ImageConversionService $imageConversion,
        private readonly SendSmsCodeAction $sendSmsCode,
        private readonly TariffService $tariffService,
        private readonly TariffRequestService $tariffRequestService,
        private readonly StoreService $storeService,
        private readonly ListingRepositoryInterface $listingRepository,
        private readonly FavoriteRepositoryInterface $favoriteRepository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->userRepository->paginate($filters);
    }

    /**
     * Поиск пользователей для селекта владельца в формах create админки.
     *
     * @return \Illuminate\Support\Collection<int, array{id:int, name:string|null, phone:string}>
     */
    public function searchForSelect(string $query, int $limit = 20): \Illuminate\Support\Collection
    {
        return $this->userRepository->searchForSelect($query, $limit);
    }

    /**
     * Создание пользователя из админки.
     * role=user — клиент приложения (SMS / активация); admin|manager —
     * вход в панель по email+пароль, всегда сразу активны.
     */
    public function store(array $data): User
    {
        $role = $data['role'] ?? 'user';
        unset($data['role'], $data['password_confirmation']);

        $isStaff = in_array($role, ['admin', 'manager'], true);
        $activation = $isStaff ? 'active' : ($data['activation'] ?? 'active');
        unset($data['activation']);

        if (($data['avatar'] ?? null) instanceof UploadedFile) {
            $data['avatar'] = $this->imageConversion->toWebp(
                $data['avatar'],
                dir: 'avatars',
                aspect: 1.0,
                maxWidth: 400,
                maxBytes: 81920,
            );
        }

        if ($isStaff) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['email']);
            // Клиент входит только по SMS — колонка password NOT NULL
            $data['password'] = Hash::make(Str::random(40));
        }

        try {
            // Роль выставляется аргументом, не из массива формы.
            $user = $this->userRepository->createWithRole($data, $role, $activation === 'active');
        } catch (UniqueConstraintViolationException) {
            if (! empty($data['avatar'])) {
                Storage::disk('public')->delete($data['avatar']);
            }
            throw ValidationException::withMessages([
                'phone' => __('messages.phone_already_registered'),
            ]);
        }

        if (! $isStaff && $activation === 'sms') {
            try {
                $this->sendSmsCode->execute($user->phone);
            } catch (ValidationException) {
                // Кулдаун — пользователь уже создан, код запросится при входе.
            }
        }

        return $user;
    }

    /** Живая проверка номера в форме создания. */
    public function phoneStatus(string $phone): array
    {
        $existing = $this->userRepository->findByPhone($phone);

        return [
            'available' => $existing === null,
            'user_id'   => $existing?->id,
        ];
    }

    public function update(User $user, array $data): User
    {
        return $this->userRepository->update($user, $data);
    }

    /** Флаг прохождения онбординга — синхронизируется между устройствами. */
    public function setOnboardingCompleted(User $user, bool $completed): User
    {
        return $this->userRepository->setOnboardingCompleted($user, $completed);
    }

    /**
     * Сводка для мобильного профиля: is_premium/tariff/store/stats
     * (mobile_docs/BACKEND_API.md §2).
     *
     * @return array{is_premium: bool, tariff: array|null, tariff_request: array|null, store: array|null, stats: array}
     */
    public function profileSummary(User $user): array
    {
        $usage = $this->tariffService->usageSummary($user);
        $tariff = $usage['tariff'];
        unset($usage['tariff']);

        return [
            'is_premium' => $tariff !== null && ! $tariff->is_free,
            // price / can_have_store / can_see_wholesale — те же поля, что в
            // каталоге /v1/tariffs: по ним мобилка решает, показывать ли
            // «Мой магазин» и вкладку «Опт», не запрашивая каталог отдельно
            'tariff'     => $tariff ? [
                ...$usage,
                'name'              => $tariff->name,
                'price'             => (float) $tariff->price,
                'can_have_store'    => $tariff->canHaveStore(),
                'can_see_wholesale' => $tariff->canSeeWholesale(),
            ] : null,
            // Пока заявка в статусе pending, мобилка показывает «На рассмотрении»
            // вместо кнопки смены тарифа, а после отказа — комментарий админа
            'tariff_request' => $this->tariffRequestService->forProfile($user),
            'store'      => $this->storeService->forProfile($user, $tariff?->canHaveStore() ?? false),
            'stats'      => [
                'views_count'       => $this->listingRepository->sumViewsByUser($user->id),
                'likes_count'       => $this->favoriteRepository->countForUser($user->id),
                'premium_days_left' => $usage['days_left'],
            ],
        ];
    }

    /** Карточка пользователя в админке: последние объявления + счётчики. */
    public function profileOverview(User $user): array
    {
        return $this->userRepository->profileOverview($user);
    }

    public function delete(User $user): void
    {
        $this->userRepository->delete($user);
    }

    /**
     * Аватар: квадрат 400×400 WebP, старый файл удаляется.
     */
    public function updateAvatar(User $user, UploadedFile $file): User
    {
        $path = $this->imageConversion->toWebp(
            $file,
            dir: 'avatars',
            aspect: 1.0,
            maxWidth: 400,
            maxBytes: 81920,
        );

        $this->deleteAvatarFile($user);

        return $this->userRepository->update($user, ['avatar' => $path]);
    }

    public function removeAvatar(User $user): User
    {
        $this->deleteAvatarFile($user);

        return $this->userRepository->update($user, ['avatar' => null]);
    }

    private function deleteAvatarFile(User $user): void
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }
    }

    public function block(User $user, ?string $reason): void
    {
        $this->userRepository->block($user, $reason);
    }

    public function unblock(User $user): void
    {
        $this->userRepository->unblock($user);
    }
}
