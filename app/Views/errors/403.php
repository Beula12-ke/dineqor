<?php
$title = $title ?? 'Access Denied';

ob_start();
?>
<section class="flex items-center justify-center px-6 py-24">
    <div class="w-full max-w-md text-center">
        <p class="text-6xl font-extrabold tracking-tight text-secondary">403</p>
        <h1 class="mt-3 text-2xl font-bold text-secondary">Access denied</h1>
        <p class="mt-2 text-sm text-slate-500">You do not have permission to view this page.</p>
        <a href="<?= e(url('/')) ?>"
           class="mt-8 inline-block rounded-lg bg-secondary px-5 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:opacity-90">
            Back to home
        </a>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
view('layout', ['title' => $title, 'content' => $content]);
