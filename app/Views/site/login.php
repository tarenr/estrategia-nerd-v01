<?php

/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/site/login.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Tela de login
 * @description Tela de login com visual neon/tech (igual ao repositório de referência).
 * @usage       Renderizada por AuthController em GET /login.
 * @notes       Depende de public/assets/css/login.css e public/assets/js/login.js carregados no layout.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Support\Csrf;

$usuarioOld = (string)($old['usuario'] ?? '');
$errorMsg = isset($error) && $error ? (string)$error : '';
?>

<div class="min-h-[calc(100vh-64px)] relative overflow-hidden flex items-center justify-center px-4 py-10 bg-dark-bg">
    <div class="absolute inset-0 z-0 bg-animated pointer-events-none"></div>
    <div class="absolute inset-0 z-0 opacity-[0.03] grid-pattern"></div>

    <main class="relative z-10 w-full max-w-md px-4 py-8" role="main">
        <div class="login-card bg-slate-900/90 backdrop-blur-xl border border-cyan-500/20 rounded-2xl shadow-2xl shadow-cyan-500/10 relative overflow-hidden">
            <div class="p-8">
                <header class="text-center mb-8">
                    <div class="logo-icon w-16 h-16 mx-auto mb-4 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                        <i class="fa-solid fa-brain text-white text-3xl" aria-hidden="true"></i>
                        <span class="sr-only">Logo</span>
                    </div>

                    <h1 class="font-orbitron text-2xl font-black text-white tracking-wider mb-1">
                        ADMIN <span class="text-cyan-400">NERD</span>
                    </h1>

                    <p class="text-gray-500 text-xs uppercase tracking-[0.2em]">Área Administrativa</p>
                </header>

                <?php if ($errorMsg !== ''): ?>
                    <div class="alert-error mb-6 p-4 bg-red-500/10 border border-red-500/30 rounded-xl flex items-start gap-3 text-red-400 animate-shake erro-box" role="alert" aria-live="polite">
                        <span class="text-xl flex-shrink-0" aria-hidden="true">⚠️</span>
                        <span class="text-sm font-medium leading-relaxed"><?= htmlspecialchars($errorMsg) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('/login') ?>" id="loginForm" class="space-y-5" novalidate>
                    <?= Csrf::field() ?>

                    <div class="space-y-2">
                        <label for="usuario" class="block text-cyan-400 text-xs font-bold uppercase tracking-wider">Usuário</label>

                        <div class="relative">
                            <input
                                type="text"
                                id="usuario"
                                name="usuario"
                                required
                                autofocus
                                autocomplete="username"
                                value="<?= htmlspecialchars($usuarioOld) ?>"
                                class="form-input w-full px-4 py-3 bg-slate-800/50 border border-cyan-500/30 rounded-xl text-white placeholder-gray-500 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 transition-all duration-300 font-rajdhani text-base"
                                placeholder="Digite seu usuário"
                                aria-describedby="usuario-help">
                            <div class="input-icon absolute right-3 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </div>

                        <span id="usuario-help" class="sr-only">Digite seu nome de usuário para acessar o painel administrativo</span>
                    </div>

                    <div class="space-y-2">
                        <label for="senha" class="block text-cyan-400 text-xs font-bold uppercase tracking-wider">Senha</label>

                        <div class="relative">
                            <input
                                type="password"
                                id="senha"
                                name="senha"
                                required
                                autocomplete="current-password"
                                class="form-input w-full px-4 py-3 bg-slate-800/50 border border-cyan-500/30 rounded-xl text-white placeholder-gray-500 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 transition-all duration-300 font-rajdhani text-base pr-12"
                                placeholder="••••••••"
                                aria-describedby="senha-help">

                            <button
                                type="button"
                                id="toggleSenha"
                                class="toggle-password absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-cyan-400 transition-colors duration-200 p-1 rounded focus:outline-none focus:ring-2 focus:ring-cyan-400"
                                aria-label="Mostrar senha"
                                aria-pressed="false">
                                <svg id="iconEye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>

                                <svg id="iconEyeOff" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                            </button>
                        </div>

                        <span id="senha-help" class="sr-only">Digite sua senha de acesso</span>
                    </div>

                    <button
                        type="submit"
                        id="btnSubmit"
                        class="btn-submit w-full py-4 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-900 font-orbitron font-bold uppercase tracking-widest rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-cyan-500/25 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none mt-2">
                        Entrar no Sistema
                    </button>
                </form>
            </div>

            <footer class="mt-6 pt-6 border-t border-cyan-500/10 px-8 pb-8">
                <div class="flex items-center justify-center gap-2 text-xs text-gray-500">
                    <span class="text-cyan-500" aria-hidden="true"></span>
                    <span>Conexão segura • SSL 256-bit</span>
                </div>

                <p class="text-center text-gray-600 text-xs mt-2">
                    Acesso restrito a administradores autorizados
                </p>
            </footer>
        </div>
    </main>
</div>