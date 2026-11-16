<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LototoActivity;

/**
 * The WhatsApp hand-over of a supervisor's follow-up: the employee's number from employees.phone (the only phone field
 * the application keeps), turned into an Indonesian international number, and a wa.me link with the message already
 * written. Nothing is sent by the application: WhatsApp opens with the text filled in and the supervisor presses send.
 */
class LototoFollowUpWhatsApp
{
    /** 0813xxxx, 813xxxx, 62813xxxx, +62 813-xxxx ... become 62813xxxx; anything that is not a plausible number gives null. */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === null || $digits === '') {
            return null;
        }
        if (str_starts_with($digits, '62')) {
            $national = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            $national = $digits;
        }
        $national = ltrim($national, '0');

        return strlen($national) >= 8 && strlen($national) <= 13 && $national[0] === '8' ? '62'.$national : null;
    }

    public static function message(Employee $employee, LototoActivity $activity, string $followUp): string
    {
        return "Halo {$employee->full_name},\n\n"
            ."Terkait aktivitas LOTOTO {$employee->lototo_number}"
            .(filled($activity->location) ? ' di '.trim((string) $activity->location) : '')
            .", terdapat tindak lanjut dari supervisor sebagai berikut:\n\n"
            .trim($followUp)
            ."\n\nMohon segera menindaklanjuti informasi tersebut.\n\nTerima kasih.";
    }

    /** The wa.me link, or null when the employee has no usable number. */
    public static function url(Employee $employee, LototoActivity $activity, string $followUp): ?string
    {
        $number = self::normalize($employee->phone);

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode(self::message($employee, $activity, $followUp)) : null;
    }
}
