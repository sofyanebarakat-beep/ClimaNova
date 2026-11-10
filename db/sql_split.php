<?php
// Splits a migration file into statements (Neon's HTTP endpoint runs one statement per query).
// Respects single quotes, double-quoted identifiers, -- and /* */ comments, and $tag$ … $tag$ bodies.
declare(strict_types=1);

function sql_split_statements(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $next = $sql[$i + 1] ?? '';
        if ($c === '-' && $next === '-') {                       // line comment
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $len : $end;
            $buf .= "\n";
            continue;
        }
        if ($c === '/' && $next === '*') {                       // block comment
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            continue;
        }
        if ($c === "'" || $c === '"') {                          // quoted text / identifier ('' escapes)
            $j = $i + 1;
            while ($j < $len) {
                if ($sql[$j] === $c) {
                    if (($sql[$j + 1] ?? '') === $c) { $j += 2; continue; }
                    break;
                }
                $j++;
            }
            $buf .= substr($sql, $i, $j - $i + 1);
            $i = $j;
            continue;
        }
        if ($c === '$' && preg_match('/\G\$([A-Za-z_][A-Za-z0-9_]*)?\$/', $sql, $m, 0, $i)) { // dollar-quoted body
            $tag = $m[0];
            $end = strpos($sql, $tag, $i + strlen($tag));
            $stop = $end === false ? $len : $end + strlen($tag);
            $buf .= substr($sql, $i, $stop - $i);
            $i = $stop - 1;
            continue;
        }
        if ($c === ';') {
            if (trim($buf) !== '') $out[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if (trim($buf) !== '') $out[] = trim($buf);
    return $out;
}
