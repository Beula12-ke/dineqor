<?php
use App\Core\Session;

$title = $title ?? 'Sign In';
$error = Session::getFlash('error');
$email = old('email');

ob_start();
?>
<section class="flex items-center justify-center px-6 py-16">
    <div class="w-full max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-soft">
            <h1 class="text-2xl font-extrabold tracking-tight text-secondary">Welcome back</h1>
            <p class="mt-1 text-sm text-slate-500">Sign in to manage your restaurant on Dineqor.</p>

            <?php if (is_string($error) && $error !== ''): ?>
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('/login')) ?>" class="mt-6 space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-secondary">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="<?= e($email) ?>"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-secondary">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:opacity-90">
                    Sign in
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                New to Dineqor?
                <a href="<?= e(url('/register')) ?>" class="font-semibold text-brand hover:underline">Create an account</a>
            </p>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
view('layout', ['title' => $title, 'content' => $content]);
