<?php

$models = glob('app/Models/*.php');

foreach ($models as $file) {
    $content = file_get_contents($file);
    $basename = basename($file, '.php');

    if (in_array($basename, ['ParoisseConfiguration', 'ResponsableParoisse', 'ProfilMenuPermission'])) {
        continue;
    }

    $modified = false;

    // 1. Ensure Auditable trait import
    if (!str_contains($content, 'use App\Traits\Auditable;')) {
        $content = preg_replace(
            '/namespace App\\\Models;/',
            "namespace App\\Models;\n\nuse App\\Traits\\Auditable;",
            $content,
            1
        );
        $modified = true;
    }

    // 2. Ensure SoftDeletes import
    if (!str_contains($content, 'use Illuminate\Database\Eloquent\SoftDeletes;')) {
        $content = preg_replace(
            '/namespace App\\\Models;/',
            "namespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;",
            $content,
            1
        );
        $modified = true;
    }

    // 3. Update use statements inside class
    if (preg_match('/class\s+' . $basename . '[^{]+\{([^}]+)/s', $content, $match)) {
        $classBody = $match[1];
        if (preg_match('/use\s+([^;]+);/', $classBody, $useMatch)) {
            $traits = array_map('trim', explode(',', $useMatch[1]));
            if (!in_array('Auditable', $traits)) {
                $traits[] = 'Auditable';
            }
            if (!in_array('SoftDeletes', $traits)) {
                $traits[] = 'SoftDeletes';
            }
            $traits = array_unique($traits);
            sort($traits);
            $newUse = 'use ' . implode(', ', $traits) . ';';
            if ($newUse !== $useMatch[0]) {
                $content = str_replace($useMatch[0], $newUse, $content);
                $modified = true;
            }
        }
    }

    if ($modified) {
        file_put_contents($file, $content);
        echo "Updated model: {$basename}\n";
    }
}
echo "Done applying Auditable trait to all models.\n";
