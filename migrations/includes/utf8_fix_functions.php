<?php
/**
 * Fonctions partagées — correction mojibake / latin1 → UTF-8
 *
 * Cas typique dans jomas_fouta.sql :
 *   "EmblÃ¨me" (octets C3 83 C2 A8) → "Emblème" (C3 A8)
 * Algorithme : iconv UTF-8 → ISO-8859-1 (pas mb_convert_encoding).
 */

if (!function_exists('utf8_brokenness_score')) {
    function utf8_brokenness_score($text)
    {
        if ($text === null || $text === '') {
            return 0;
        }
        $score = 0;
        if (!mb_check_encoding($text, 'UTF-8')) {
            $score += 1000;
        }
        $score += substr_count($text, 'Ã') * 10;
        $score += substr_count($text, 'â€') * 10;
        $score += substr_count($text, 'Ãƒ') * 5;
        return $score;
    }
}

if (!function_exists('utf8_fix_text')) {
    function utf8_fix_text($text)
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $best = $text;
        $best_score = utf8_brokenness_score($best);
        $current = $text;

        for ($pass = 0; $pass < 8; $pass++) {
            if ($best_score === 0) {
                break;
            }

            $try = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $current);
            if ($try === false || $try === $current) {
                break;
            }

            // Ne pas casser du UTF-8 déjà correct (ex. Rétroviseur → octets latin1 invalides en UTF-8)
            if (!mb_check_encoding($try, 'UTF-8')) {
                break;
            }

            $try_score = utf8_brokenness_score($try);
            if ($try_score >= $best_score) {
                break;
            }

            $best = $try;
            $best_score = $try_score;
            $current = $try;
        }

        // Octets latin1 bruts (UTF-8 invalide en entrée)
        if ($best_score > 0 && !mb_check_encoding($text, 'UTF-8')) {
            $try = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
            if ($try !== false && mb_check_encoding($try, 'UTF-8')) {
                $try_score = utf8_brokenness_score($try);
                if ($try_score < $best_score) {
                    $best = $try;
                }
            }
        }

        return $best;
    }
}

if (!function_exists('utf8_fix_from_binary')) {
    function utf8_fix_from_binary($bytes)
    {
        if ($bytes === '') {
            return '';
        }

        return utf8_fix_text($bytes);
    }
}
