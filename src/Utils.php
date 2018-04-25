<?php

namespace carono\yandex;

class Utils
{
    /**
     * @param \DateTime $date
     * @return string
     */
    public static function formatDate(\DateTime $date)
    {
        return $date->format('Y-m-d') . 'T' . $date->format('H:i:s') . '.000' . $date->format('P');
    }

    /**
     * @param \DateTime $date
     * @return string
     */
    public static function formatDateForMWS(\DateTime $date)
    {
        return $date->format('Y-m-d') . 'T' . $date->format('H:i:s') . '.000Z';
    }
}