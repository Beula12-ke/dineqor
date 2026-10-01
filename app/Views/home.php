<?php
$title = $title ?? 'Home';

ob_start();
?>
<section class="px-6 py-24">
    <div class="mx-auto w-full max-w-3xl text-center">
        <span class="inline-flex items-center rounded-full bg-brand/10 px-3 py-1 text-xs font-semibold text-brand">
            Dineqor is running
        </span>
        <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-secondary sm:text-5xl">
            One platform.<br>Every restaurant.
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base text-slate-500">
            Dineqor gives restaurants an online storefront, ordering, reservations and delivery —
            all managed from a single multi-tenant dashboard.
        </p>
        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="<?= e(url('/register')) ?>"
               class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:opacity-90">
                Get started
            </a>
            <a href="<?= e(url('/login')) ?>"
               class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-secondary transition hover:bg-slate-50">
                Sign in
            </a>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
view('layout', ['title' => $title, 'content' => $content]);
