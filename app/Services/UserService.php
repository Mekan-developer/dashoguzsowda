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
        private readonly StoreService $storeService,
        private readonly ListingRepositoryInterface $listingRepository,
        private readonly FavoriteRepositoryInterface $favoriteRepository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->userRepository->paginate($filters);
    }

    /**
     * Создание пользователя из админки: только по телефону, без пароля.
     * activation: active — активен сразу; sms — код через локальный модем,
     * активация при первом входе в приложение.
     */
    public function store(array $data): User
    {
        $activation = $data['activation'] ?? 'active';
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

        // Пароль пользователем не используется (вход только по SMS) — колонка NOT NULL
        $data['password'] = Hash::make(Str::random(40));

        try {
            // Роль и подтверждение номера передаются аргументами, а не в массиве:
            // их нельзя подмешать к валидированным данным формы.
            $user = $this->userRepository->createWithRole($data, 'user', $activation === 'active');
        } catch (UniqueConstraintViolationException) {
            // Гонка: номер заняли между живой проверкой и submit-ом
            if (! empty($data['avatar'])) {
                Storage::disk('public')->delete($data['avatar']);
            }
            throw ValidationException::withMessages([
                'phone' => __('messages.phone_already_registered'),
            ]);
        }

        if ($activation === 'sms') {
            try {
                $this->sendSmsCode->execute($user->phone); // → SmsCodeRequested → SendSmsCode → локальный модем
            } catch (ValidationException) {
                // Кулдаун повторной отправки на этот номер — пользователь уже
                // создан, код запросится заново при первом входе. Не откатываем.
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
     * @return array{is_premium: bool, tariff: array|null, store: array|null, stats: array}
     */
    public function profileSummary(User $user): array
    {
        $usage = $this->tariffService->usageSummary($user);
        $tariff = $usage['tariff'];
        unset($usage['tariff']);

        return [
            'is_premium' => $tariff !== null && ! $tariff->is_free,
            'tariff'     => $tariff ? [...$usage, 'name' => $tariff->name] : null,
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
