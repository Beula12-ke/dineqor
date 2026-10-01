<?php
use App\Core\Session;

$title = $title ?? 'Page Not Found';
$error = Session::getFlash('error');

ob_start();
?>
<section class="flex items-center justify-center px-6 py-24">
    <div class="w-full max-w-md text-center">
        <p class="text-6xl font-extrabold tracking-tight text-brand">404</p>
        <h1 class="mt-3 text-2xl font-bold text-secondary">Page not found</h1>
        <p class="mt-2 text-sm text-slate-500">
            <?= is_string($error) && $error !== '' ? e($error) : 'The page or restaurant you are looking for does not exist.' ?>
        </p>
        <a href="<?= e(url('/')) ?>"
           class="mt-8 inline-block rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:opacity-90">
            Back to home
        </a>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
view('layout', ['title' => $title, 'content' => $content]);
