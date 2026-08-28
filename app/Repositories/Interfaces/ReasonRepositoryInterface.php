<?php

namespace App\Repositories\Interfaces;

use App\Models\ComplaintReason;
use App\Models\RejectionReason;
use Illuminate\Database\Eloquent\Collection;

/**
 * Справочники причин: отклонения (объявление/ролик/отзыв) и жалоб.
 *
 * Оба списка читаются из четырёх контроллеров админки и из мобильного API,
 * поэтому вынесены в один репозиторий вместо RejectionReason::where(...)
 * россыпью по контроллерам.
 */
interface ReasonRepositoryInterface
{
    /** Все причины отклонения указанного типа, включая выключенные. */
    public function rejectionReasonsByType(string $type): Collection;

    /** Активные причины отклонения для типа: listing|video|review. */
    public function activeRejectionReasons(string $type): Collection;

    public function createRejectionReason(array $data): RejectionReason;
    public function updateRejectionReason(RejectionReason $reason, array $data): RejectionReason;
    public function deleteRejectionReason(RejectionReason $reason): void;

    /** @return Collection<int, ComplaintReason> */
    public function allComplaintReasons(): Collection;

    public function createComplaintReason(array $data): ComplaintReason;
    public function updateComplaintReason(ComplaintReason $reason, array $data): ComplaintReason;
    public function deleteComplaintReason(ComplaintReason $reason): void;
}
