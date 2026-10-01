<?php
$title = $title ?? 'Platform Dashboard';

ob_start();
?>
<section class="px-6 py-16">
    <div class="mx-auto w-full max-w-6xl">
        <h1 class="text-3xl font-extrabold tracking-tight text-secondary">Platform Dashboard</h1>
        <p class="mt-2 text-sm text-slate-500">
            Platform administration is wired up and ready for feature development.
        </p>

        <div class="mt-8 grid gap-6 sm:grid-cols-3">
            <?php foreach (['Restaurants', 'Orders today', 'Revenue'] as $card): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
                    <p class="text-sm font-medium text-slate-500"><?= e($card) ?></p>
                    <p class="mt-2 text-3xl font-extrabold text-secondary">&mdash;</p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
view('layout', ['title' => $title, 'content' => $content]);
