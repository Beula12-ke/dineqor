<?php
use App\Core\Session;

$title = $title ?? 'Create Account';
$error = Session::getFlash('error');
$fullName = old('full_name');
$email = old('email');

ob_start();
?>
<section class="flex items-center justify-center px-6 py-16">
    <div class="w-full max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-soft">
            <h1 class="text-2xl font-extrabold tracking-tight text-secondary">Create your account</h1>
            <p class="mt-1 text-sm text-slate-500">Order from your favourite restaurants and track every order.</p>

            <?php if (is_string($error) && $error !== ''): ?>
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('/register')) ?>" class="mt-6 space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label for="full_name" class="mb-1 block text-sm font-medium text-secondary">Full name</label>
                    <input id="full_name" name="full_name" type="text" autocomplete="name" required
                           value="<?= e($fullName) ?>"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-secondary">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="<?= e($email) ?>"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-secondary">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <p class="mt-1 text-xs text-slate-400">At least 8 characters.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium text-secondary">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password"
                           autocomplete="new-password" required minlength="8"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:opacity-90">
                    Create account
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                Already have an account?
                <a href="<?= e(url('/login')) ?>" class="font-semibold text-brand hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
view('layout', ['title' => $title, 'content' => $content]);
