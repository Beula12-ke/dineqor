<?php
/** @var string $heading */
/** @var string $subtitle */
$title = $title ?? $heading;
$subtitle = $subtitle ?? 'This area is wired up and ready for feature development.';

ob_start();
?>
<section class="px-6 py-16">
    <div class="mx-auto w-full max-w-3xl">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-soft">
            <h1 class="text-2xl font-extrabold tracking-tight text-secondary"><?= e($heading) ?></h1>
            <p class="mt-2 text-sm text-slate-500"><?= e($subtitle) ?></p>

            <form method="POST" action="<?= e(url('/logout')) ?>" class="mt-8">
                <?= csrf_field() ?>
                <button type="submit"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-secondary transition hover:bg-slate-50">
                    Sign out
                </button>
            </form>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
view('layout', ['title' => $title, 'content' => $content]);
