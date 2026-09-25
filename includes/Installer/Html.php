<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Formularbausteine des Web-Installers
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

use PowerPHPBoard\Security;

/**
 * Erzeugt Bootstrap-5-Formularfelder. Jedes Feld hat eine eindeutige id,
 * die dem name-Attribut entspricht (für Tests und Videoaufnahmen).
 * Alle Werte werden escaped.
 */
final class Html
{
    /**
     * Eingabefeld mit Label, Hilfetext und Fehlermeldung.
     *
     * @param array<string, string> $errors Fehlermeldungen je Feldname
     * @param array<string, string|int|bool> $attributes zusätzliche Attribute (true = ohne Wert)
     */
    public static function input(
        string $name,
        string $label,
        string $value,
        array $errors,
        array $attributes = [],
        string $help = '',
        string $hint = ''
    ): string {
        $error = $errors[$name] ?? '';
        $attributes += ['type' => 'text'];

        return '<div class="mb-3">'
            . self::label($name, $label, isset($attributes['required']))
            . '<input id="' . Security::escapeAttr($name) . '" name="' . Security::escapeAttr($name) . '"'
            . ' class="form-control' . ($error !== '' ? ' is-invalid' : '') . '"'
            . ' value="' . Security::escapeAttr($value) . '"'
            . self::attributes($attributes)
            . self::describedBy($name, $help, $error)
            . '>'
            . self::help($name, $help)
            . self::feedback($name, $error !== '' ? $error : $hint)
            . '</div>';
    }

    /**
     * Auswahlliste.
     *
     * @param array<string, string> $options Wert => Beschriftung
     * @param array<string, string> $errors
     */
    public static function select(
        string $name,
        string $label,
        array $options,
        string $selected,
        array $errors,
        string $help = ''
    ): string {
        $error = $errors[$name] ?? '';
        $html = '<div class="mb-3">'
            . self::label($name, $label, true)
            . '<select id="' . Security::escapeAttr($name) . '" name="' . Security::escapeAttr($name) . '"'
            . ' class="form-select' . ($error !== '' ? ' is-invalid' : '') . '" required'
            . self::describedBy($name, $help, $error) . '>';

        foreach ($options as $value => $text) {
            $html .= '<option value="' . Security::escapeAttr($value) . '"'
                . ($value === $selected ? ' selected' : '') . '>'
                . Security::escape($text) . '</option>';
        }

        return $html . '</select>'
            . self::help($name, $help)
            . self::feedback($name, $error !== '' ? $error : 'Bitte wählen Sie einen Eintrag aus.')
            . '</div>';
    }

    /**
     * Fehlermeldung als Bootstrap-Alert (leer, wenn keine Meldung).
     */
    public static function alert(string $message, string $type = 'danger', string $id = 'installer-message'): string
    {
        if ($message === '') {
            return '';
        }

        $icon = match ($type) {
            'success' => 'bi-check-circle-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'info' => 'bi-info-circle-fill',
            default => 'bi-x-octagon-fill',
        };

        return '<div id="' . Security::escapeAttr($id) . '" class="alert alert-' . Security::escapeAttr($type)
            . ' d-flex align-items-start gap-2" role="alert">'
            . '<i class="bi ' . $icon . ' flex-shrink-0" aria-hidden="true"></i>'
            . '<div>' . Security::escape($message) . '</div></div>';
    }

    private static function label(string $name, string $label, bool $required): string
    {
        return '<label for="' . Security::escapeAttr($name) . '" class="form-label fw-semibold">'
            . Security::escape($label)
            . ($required ? ' <span class="text-danger-aa" aria-hidden="true">*</span>' : '')
            . '</label>';
    }

    /**
     * @param array<string, string|int|bool> $attributes
     */
    private static function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $key => $value) {
            if ($value === false) {
                continue;
            }
            $html .= ' ' . Security::escapeAttr($key);
            if ($value !== true) {
                $html .= '="' . Security::escapeAttr((string) $value) . '"';
            }
        }

        return $html;
    }

    private static function describedBy(string $name, string $help, string $error): string
    {
        $ids = [];
        if ($help !== '') {
            $ids[] = $name . '-help';
        }
        if ($error !== '') {
            $ids[] = $name . '-error';
        }

        return $ids === [] ? '' : ' aria-describedby="' . Security::escapeAttr(implode(' ', $ids)) . '"';
    }

    private static function help(string $name, string $help): string
    {
        if ($help === '') {
            return '';
        }

        return '<div id="' . Security::escapeAttr($name . '-help') . '" class="form-text">' . Security::escape($help) . '</div>';
    }

    private static function feedback(string $name, string $message): string
    {
        if ($message === '') {
            return '';
        }

        return '<div id="' . Security::escapeAttr($name . '-error') . '" class="invalid-feedback">' . Security::escape($message) . '</div>';
    }
}
