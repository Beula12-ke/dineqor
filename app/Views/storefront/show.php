<?php
/** @var array $restaurant */
$title = $title ?? 'Restaurant';

ob_start();
?>
<section class="px-6 py-16">
    <div class="mx-auto w-full max-w-5xl">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-soft">
            <div class="h-32 w-full"
                 style="background: linear-gradient(120deg, <?= e($restaurant['primary_color'] ?? '#e63946') ?>, <?= e($restaurant['secondary_color'] ?? '#1d3557') ?>);"></div>
            <div class="p-8">
                <h1 class="text-2xl font-extrabold tracking-tight text-secondary"><?= e($restaurant['name']) ?></h1>
                <p class="mt-1 text-sm text-slate-500">
                    <?= e($restaurant['cuisine'] ?? 'Restaurant') ?><?= !empty($restaurant['city']) ? ' &middot; ' . e($restaurant['city']) : '' ?>
                </p>
                <p class="mt-4 text-sm leading-relaxed text-slate-600">
                    <?= e($restaurant['description'] ?? 'Storefront placeholder — menu, ordering and reservations come next.') ?>
                </p>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
view('layout', ['title' => $title, 'content' => $content]);
