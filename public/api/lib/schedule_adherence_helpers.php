<?php
/**
 * Helpers: does a schedule row apply today, and classify today's adherence for doctor view.
 *
 * Confirmation time for doctors is shown in Philippine Time (Asia/Manila, UTC+8, no DST).
 * Set PHARSAYO_DB_TIMEZONE if MySQL datetime strings are not in PHP's default_timezone (e.g. UTC).
 */

function pharsayo_adherence_source_timezone(): string {
    $env = getenv('PHARSAYO_DB_TIMEZONE');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    $def = @date_default_timezone_get();
    return (is_string($def) && $def !== '') ? $def : 'UTC';
}

/**
 * Patient confirmation instant, shown in Philippine Time (PHT), 12-hour clock.
 *
 * @return string|null e.g. "2:35 AM PHT"
 */
function pharsayo_format_adherence_confirmed_philippine(?string $rawFromDb): ?string {
    if ($rawFromDb === null || trim($rawFromDb) === '') {
        return null;
    }
    try {
        $dt = new DateTimeImmutable(trim($rawFromDb), new DateTimeZone(pharsayo_adherence_source_timezone()));
        $dt = $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    } catch (Throwable $e) {
        return null;
    }
    return $dt->format('g:i A') . ' PHT';
}

function pharsayo_days_include_today(string $daysCsv): bool {
    $today = date('D');
    foreach (explode(',', $daysCsv) as $part) {
        $p = trim($part);
        if ($p !== '' && strcasecmp($p, $today) === 0) {
            return true;
        }
    }
    return false;
}

function pharsayo_date_range_includes_today(?string $start, ?string $end): bool {
    $today = date('Y-m-d');
    if ($start !== null && trim($start) !== '' && $today < $start) {
        return false;
    }
    if ($end !== null && trim($end) !== '' && $today > $end) {
        return false;
    }
    return true;
}

/**
 * @param array{days_of_week?:string,start_date?:string,end_date?:string,log_taken?:mixed,log_responded_at?:mixed,log_scheduled_time?:mixed} $row
 */
function pharsayo_adherence_today_status(array $row): string {
    if (!pharsayo_days_include_today((string)($row['days_of_week'] ?? ''))) {
        return 'not_scheduled_today';
    }
    if (!pharsayo_date_range_includes_today($row['start_date'] ?? null, $row['end_date'] ?? null)) {
        return 'not_scheduled_today';
    }
    $lt = $row['log_taken'] ?? null;
    if ($lt === null || $lt === '') {
        return 'pending';
    }
    if ((int)$lt === 0) {
        return 'marked_not_taken';
    }
    $resp = $row['log_responded_at'] ?? null;
    $sched = $row['log_scheduled_time'] ?? null;
    if ($resp === null || $resp === '' || $sched === null || $sched === '') {
        return 'taken_time_unknown';
    }

    try {
        $srcTz = new DateTimeZone(pharsayo_adherence_source_timezone());
        $phtTz = new DateTimeZone('Asia/Manila');

        // Response is in DB timezone (usually UTC on AwardSpace)
        $dtResp = new DateTimeImmutable((string)$resp, $srcTz);
        // Scheduled time from UI is local (PHT)
        $dtSched = new DateTimeImmutable((string)$sched, $phtTz);

        $tResp = $dtResp->getTimestamp();
        $tSched = $dtSched->getTimestamp();

        $diffMin = ($tResp - $tSched) / 60;
        // On time: within 30 minutes before or after scheduled dose time.
        if ($diffMin >= -30 && $diffMin <= 30) {
            return 'taken_on_time';
        }
        return 'taken_late';
    } catch (Throwable $e) {
        return 'taken_time_unknown';
    }
}
