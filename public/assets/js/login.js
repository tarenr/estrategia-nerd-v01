/**
 * -----------------------------------------------------------------------------
 * @file        public/assets/js/login.js
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Interações da tela de login
 * @description Toggle de senha (SVG eye/eye-off), estado de loading no submit e efeitos visuais.
 * @usage       Carregado no layout apenas na rota /login.
 * @notes       IDs esperados: loginForm, btnSubmit, toggleSenha, iconEye, iconEyeOff, senha, usuario.
 * -----------------------------------------------------------------------------
 */

document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('loginForm');
  const btnSubmit = document.getElementById('btnSubmit');
  const toggleSenha = document.getElementById('toggleSenha');
  const senhaInput = document.getElementById('senha');
  const usuarioInput = document.getElementById('usuario');

  if (!form || !btnSubmit || !senhaInput || !usuarioInput) return;

  const iconEye = document.getElementById('iconEye');
  const iconEyeOff = document.getElementById('iconEyeOff');

  // Toggle password visibility (repo-like: swap SVGs)
  if (toggleSenha) {
    toggleSenha.addEventListener('click', function () {
      const isPassword = senhaInput.getAttribute('type') === 'password';
      senhaInput.setAttribute('type', isPassword ? 'text' : 'password');

      const pressed = isPassword ? 'true' : 'false';
      this.setAttribute('aria-pressed', pressed);
      this.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Mostrar senha');

      if (iconEye && iconEyeOff) {
        iconEye.classList.toggle('hidden', isPassword);
        iconEyeOff.classList.toggle('hidden', !isPassword);
      }

      senhaInput.focus();
    });
  }

  // Loading state no submit (repo-safe: disable button)
  form.addEventListener('submit', function () {
    btnSubmit.disabled = true;

    const btnText = btnSubmit.querySelector('.btn-text');
    const btnLoading = btnSubmit.querySelector('.btn-loading');

    if (btnText) btnText.style.display = 'none';
    if (btnLoading) btnLoading.style.display = 'inline';
  });

  // Efeito de foco nos inputs (scale no wrapper imediato, se existir)
  const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
  inputs.forEach((input) => {
    input.addEventListener('focus', function () {
      if (this.parentElement) this.parentElement.style.transform = 'scale(1.02)';
    });

    input.addEventListener('blur', function () {
      if (this.parentElement) this.parentElement.style.transform = 'scale(1)';
    });
  });

  // Shake animation se tiver erro (reforço visual)
  const erroBox = document.querySelector('.erro-box');
  if (erroBox) {
    setTimeout(() => {
      erroBox.style.animation = 'none';
    }, 500);
  }

  // Focus no usuário se estiver vazio
  if (!usuarioInput.value) {
    usuarioInput.focus();
  }
});