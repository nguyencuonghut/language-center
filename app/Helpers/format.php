<?php

if (!function_exists('vnd')) {
    function vnd($n): string {
        if ($n === null) return '—';
        return number_format((int)$n, 0, ',', '.');
    }
}

if (!function_exists('dmy')) {
    function dmy($d): string {
        if (!$d) return '—';
        try {
            return \Carbon\Carbon::parse($d)->format('d/m/Y');
        } catch (\Exception $e) {
            return (string)$d;
        }
    }
}
