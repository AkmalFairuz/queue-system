<?php

namespace App\Support;

enum TicketStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case Serving = 'serving';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function terminalValues(): array
    {
        return [
            self::Completed->value,
            self::Skipped->value,
            self::Cancelled->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [
            self::Called->value,
            self::Serving->value,
        ];
    }
}
