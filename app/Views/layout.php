<?php
/**
 * Base HTML shell. Expects:
 *   $content  (string) buffered inner markup
 *   $title    (string) page title
 *   $brand    (string) optional primary colour override
 */
$title       = $title ?? 'Dineqor';
$brand       = $brand ?? '#e63946';
$content     = $content ?? '';
$csrf        = $csrf ?? csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($title) ?> &middot; <?= e(config('app.name', 'Dineqor')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '<?= e($brand) ?>',
                        secondary: '#1d3557'
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif']
                    },
                    boxShadow: {
                        soft: '0 10px 30px -12px rgba(29, 53, 87, 0.25)'
                    }
                }
            }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="h-full bg-slate-50 font-sans text-secondary antialiased">
    <div class="flex min-h-full flex-col">
        <header class="border-b border-slate-200 bg-white/80 backdrop-blur">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                <a href="<?= e(url('/')) ?>" class="flex items-center gap-2 text-lg font-extrabold tracking-tight">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand text-white shadow-soft">D</span>
                    <span>Dineqor</span>
                </a>
                <nav class="flex items-center gap-6 text-sm font-medium text-slate-600">
                    <a href="<?= e(url('/login')) ?>" class="transition hover:text-brand">Sign in</a>
                    <a href="<?= e(url('/register')) ?>"
                       class="rounded-lg bg-brand px-4 py-2 text-white shadow-soft transition hover:opacity-90">Get started</a>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <?= $content ?>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto w-full max-w-6xl px-6 py-6 text-xs text-slate-500">
                &copy; <?= date('Y') ?> Dineqor. All rights reserved.
            </div>
        </footer>
    </div>
</body>
</html>
