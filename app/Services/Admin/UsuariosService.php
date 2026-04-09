<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\UsuarioRepository;

final class UsuariosService
{
    private const PAPEL_OPTIONS = [
        'admin' => 'Administrador',
        'editor' => 'Editor',
    ];

    private const STATUS_OPTIONS = [
        'ativo' => 'Ativo',
        'inativo' => 'Inativo',
    ];

    private const AVATAR_ICON_OPTIONS = [
        'fa-solid fa-user' => 'Usuario',
        'fa-solid fa-user-tie' => 'Executivo',
        'fa-solid fa-user-astronaut' => 'Astronauta',
        'fa-solid fa-user-ninja' => 'Ninja',
        'fa-solid fa-shield-halved' => 'Guardiao',
        'fa-solid fa-pen-nib' => 'Editorial',
        'fa-solid fa-gamepad' => 'Games',
        'fa-solid fa-headset' => 'Suporte',
    ];

    public function __construct(
        private UsuarioRepository $usuarios,
        private MidiaService $midia,
    ) {
    }

    public function getIndexViewModel(array $query = [], ?int $currentUserId = null): array
    {
        $filters = $this->normalizeFilters($query);
        $page = $this->clampInt((int) ($query['page'] ?? 1), 1, 9999);
        $perPage = $this->clampInt((int) ($query['per_page'] ?? 10), 5, 50);
        [$sort, $dir] = $this->normalizeSortDir((string) ($query['sort'] ?? 'criado_em'), (string) ($query['dir'] ?? 'desc'));

        $summaryItems = $this->usuarios->listAdmin($filters);
        $pagination = $this->usuarios->paginateAdmin($filters, $page, $perPage, $sort, $dir);
        $pagination['items'] = $this->decorateIndexItems($pagination['items'] ?? [], $currentUserId);

        return [
            'title' => 'Usuarios',
            'items' => $pagination['items'],
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'pagination' => $pagination,
            'summary' => $this->buildSummary($summaryItems),
            'current_user_id' => $currentUserId,
            'active_admins_total' => $this->usuarios->countActiveAdmins(),
            'flash' => trim((string) ($query['flash'] ?? '')),
        ];
    }

    public function getCreateViewModel(array $old = [], array $errors = []): array
    {
        return $this->buildFormViewModel('create', $this->normalizeForm($old), $errors);
    }

    public function getEditViewModel(int $id, array $old = [], array $errors = []): ?array
    {
        $usuario = $this->usuarios->findById($id);
        if ($usuario === null) {
            return null;
        }

        $form = $old !== []
            ? array_replace($this->mapUsuarioToForm($usuario), $this->normalizeForm($old, $id))
            : $this->mapUsuarioToForm($usuario);

        return $this->buildFormViewModel('edit', $form, $errors, $usuario);
    }

    public function getDeleteViewModel(int $id, ?int $currentUserId = null): ?array
    {
        $usuario = $this->usuarios->findById($id);
        if ($usuario === null) {
            return null;
        }

        $postsCount = $this->usuarios->countPostsByAuthor($id);
        $isCurrentUser = $currentUserId !== null && $id === $currentUserId;
        $isLastActiveAdmin = $this->isActiveAdmin($usuario) && $this->usuarios->countActiveAdmins($id) <= 0;

        return [
            'title' => 'Excluir Usuario',
            'usuario' => $usuario,
            'posts_count' => $postsCount,
            'is_current_user' => $isCurrentUser,
            'is_last_active_admin' => $isLastActiveAdmin,
        ];
    }

    public function createUsuario(array $input, array $files): array
    {
        $form = $this->normalizeForm($input);
        $errors = $this->validateForm($form, null, true, null);
        $form = $this->applyAvatar($form, $files, null, $errors);

        if ($errors !== []) {
            return ['ok' => false, 'viewModel' => $this->buildFormViewModel('create', $form, $errors)];
        }

        $id = $this->usuarios->insertAdmin([
            'usuario' => $form['usuario'],
            'nome' => $form['nome'],
            'email' => $form['email'],
            'papel' => $form['papel'],
            'status' => $form['status'],
            'avatar_tipo' => $form['avatar_tipo'],
            'avatar_icone' => $form['avatar_icone'],
            'avatar_cor' => $form['avatar_cor'],
            'avatar_imagem' => $form['avatar_imagem'],
            'avatar_focal_x' => $form['avatar_focal_x'],
            'avatar_focal_y' => $form['avatar_focal_y'],
            'senha' => password_hash($form['senha'], PASSWORD_DEFAULT),
        ]);

        return ['ok' => true, 'id' => $id];
    }

    public function updateUsuario(int $id, array $input, array $files, ?int $currentUserId = null): array
    {
        $usuario = $this->usuarios->findById($id);
        if ($usuario === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $form = $this->normalizeForm($input, $id);
        $errors = $this->validateForm($form, $id, false, $currentUserId);

        if ($this->wouldRemoveLastActiveAdmin($usuario, $form)) {
            $errors['status'] = 'Mantenha ao menos um administrador ativo no sistema.';
        }

        $form = $this->applyAvatar($form, $files, $usuario, $errors);

        if ($errors !== []) {
            return ['ok' => false, 'viewModel' => $this->buildFormViewModel('edit', $form, $errors, $usuario)];
        }

        $payload = [
            'usuario' => $form['usuario'],
            'nome' => $form['nome'],
            'email' => $form['email'],
            'papel' => $form['papel'],
            'status' => $form['status'],
            'avatar_tipo' => $form['avatar_tipo'],
            'avatar_icone' => $form['avatar_icone'],
            'avatar_cor' => $form['avatar_cor'],
            'avatar_imagem' => $form['avatar_imagem'],
            'avatar_focal_x' => $form['avatar_focal_x'],
            'avatar_focal_y' => $form['avatar_focal_y'],
        ];

        if ($form['senha'] !== '') {
            $payload['senha'] = password_hash($form['senha'], PASSWORD_DEFAULT);
        }

        $this->usuarios->updateAdmin($id, $payload);
        $fresh = $this->usuarios->findById($id);

        return [
            'ok' => true,
            'session_user' => $currentUserId !== null && $id === $currentUserId ? $fresh : null,
        ];
    }

    public function toggleStatus(int $id, ?int $currentUserId = null): array
    {
        $usuario = $this->usuarios->findById($id);
        if ($usuario === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $currentStatus = (string) ($usuario['status'] ?? 'ativo');
        $targetStatus = $currentStatus === 'ativo' ? 'inativo' : 'ativo';

        if ($currentUserId !== null && $id === $currentUserId && $targetStatus !== 'ativo') {
            return ['ok' => false, 'flash' => 'cannot_disable_self'];
        }

        if ($this->isActiveAdmin($usuario) && $targetStatus !== 'ativo' && $this->usuarios->countActiveAdmins($id) <= 0) {
            return ['ok' => false, 'flash' => 'cannot_remove_last_admin'];
        }

        $this->usuarios->updateStatus($id, $targetStatus);
        return ['ok' => true, 'flash' => 'status_updated'];
    }

    public function deleteUsuario(int $id, ?int $currentUserId = null): array
    {
        $usuario = $this->usuarios->findById($id);
        if ($usuario === null) {
            return ['ok' => false, 'not_found' => true];
        }

        if ($currentUserId !== null && $id === $currentUserId) {
            return ['ok' => false, 'flash' => 'cannot_delete_self'];
        }

        if ($this->isActiveAdmin($usuario) && $this->usuarios->countActiveAdmins($id) <= 0) {
            return ['ok' => false, 'flash' => 'cannot_remove_last_admin'];
        }

        $fallbackAdmin = $this->usuarios->findFirstActiveAdmin($id);
        if ($fallbackAdmin !== null && $this->usuarios->countPostsByAuthor($id) > 0) {
            $this->usuarios->reassignPostsByAuthor($id, (int) ($fallbackAdmin['id'] ?? 0));
        }

        $this->removeManagedAvatar((string) ($usuario['avatar_imagem'] ?? ''));
        $this->usuarios->deleteById($id);

        return ['ok' => true, 'flash' => 'deleted'];
    }

    private function buildFormViewModel(string $mode, array $form, array $errors = [], ?array $usuario = null): array
    {
        return [
            'title' => $mode === 'edit' ? 'Editar Usuario' : 'Criar Usuario',
            'mode' => $mode,
            'form' => $form,
            'errors' => $errors,
            'usuario' => $usuario,
            'papel_options' => self::PAPEL_OPTIONS,
            'status_options' => self::STATUS_OPTIONS,
            'avatar_icon_options' => self::AVATAR_ICON_OPTIONS,
        ];
    }

    private function buildSummary(array $items): array
    {
        $total = count($items);
        $ativos = 0;
        $inativos = 0;
        $admins = 0;
        $editores = 0;
        $comFoto = 0;
        $comIcone = 0;
        $acessoHoje = 0;
        $acesso7Dias = 0;
        $novos30Dias = 0;
        $nuncaAcessaram = 0;

        $hoje = strtotime(date('Y-m-d 00:00:00')) ?: time();
        $seteDias = strtotime('-7 days') ?: time();
        $trintaDias = strtotime('-30 days') ?: time();

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'inativo');
            $papel = (string) ($item['papel'] ?? 'editor');
            $avatarTipo = (string) ($item['avatar_tipo'] ?? 'icone');
            $avatarImagem = trim((string) ($item['avatar_imagem'] ?? ''));
            $ultimoAcesso = trim((string) ($item['ultimo_acesso'] ?? ''));
            $criadoEm = trim((string) ($item['criado_em'] ?? ''));

            if ($status === 'ativo') {
                $ativos++;
            } else {
                $inativos++;
            }

            if ($papel === 'admin') {
                $admins++;
            } else {
                $editores++;
            }

            if ($avatarTipo === 'foto' && $avatarImagem !== '') {
                $comFoto++;
            } else {
                $comIcone++;
            }

            $lastTs = $ultimoAcesso !== '' ? strtotime($ultimoAcesso) : false;
            if ($lastTs === false) {
                $nuncaAcessaram++;
            } else {
                if ($lastTs >= $hoje) {
                    $acessoHoje++;
                }
                if ($lastTs >= $seteDias) {
                    $acesso7Dias++;
                }
            }

            $createdTs = $criadoEm !== '' ? strtotime($criadoEm) : false;
            if ($createdTs !== false && $createdTs >= $trintaDias) {
                $novos30Dias++;
            }
        }

        return [
            'total' => $total,
            'ativos' => $ativos,
            'inativos' => $inativos,
            'admins' => $admins,
            'editores' => $editores,
            'com_foto' => $comFoto,
            'com_icone' => $comIcone,
            'acesso_hoje' => $acessoHoje,
            'acesso_7_dias' => $acesso7Dias,
            'novos_30_dias' => $novos30Dias,
            'nunca_acessaram' => $nuncaAcessaram,
            'admins_rate' => $total > 0 ? ($admins / $total) * 100 : 0.0,
            'ativos_rate' => $total > 0 ? ($ativos / $total) * 100 : 0.0,
            'foto_rate' => $total > 0 ? ($comFoto / $total) * 100 : 0.0,
        ];
    }

    private function decorateIndexItems(array $items, ?int $currentUserId): array
    {
        $activeAdminsTotal = $this->usuarios->countActiveAdmins();

        return array_map(function (array $item) use ($currentUserId, $activeAdminsTotal): array {
            $id = (int) ($item['id'] ?? 0);
            $isCurrent = $currentUserId !== null && $id === $currentUserId;
            $isLastAdmin = $this->isActiveAdmin($item) && $activeAdminsTotal <= 1;
            $item['can_toggle'] = !$isCurrent && !$isLastAdmin;
            $item['can_delete'] = !$isCurrent && !$isLastAdmin;
            $item['is_current_user'] = $isCurrent;
            $item['is_last_active_admin'] = $isLastAdmin;
            return $item;
        }, $items);
    }

    private function mapUsuarioToForm(array $usuario): array
    {
        return [
            'id' => (int) ($usuario['id'] ?? 0),
            'nome' => trim((string) ($usuario['nome'] ?? '')),
            'usuario' => trim((string) ($usuario['usuario'] ?? '')),
            'email' => trim((string) ($usuario['email'] ?? '')),
            'papel' => trim((string) ($usuario['papel'] ?? 'admin')),
            'status' => trim((string) ($usuario['status'] ?? 'ativo')),
            'senha' => '',
            'senha_confirmacao' => '',
            'avatar_tipo' => trim((string) ($usuario['avatar_tipo'] ?? 'icone')),
            'avatar_icone' => trim((string) ($usuario['avatar_icone'] ?? 'fa-solid fa-user')),
            'avatar_cor' => trim((string) ($usuario['avatar_cor'] ?? '#38bdf8')),
            'avatar_imagem' => trim((string) ($usuario['avatar_imagem'] ?? '')),
            'avatar_focal_x' => $this->sanitizePercent((float) ($usuario['avatar_focal_x'] ?? 50.0)),
            'avatar_focal_y' => $this->sanitizePercent((float) ($usuario['avatar_focal_y'] ?? 50.0)),
            'limpar_avatar_imagem' => 0,
        ];
    }

    private function normalizeForm(array $input, int $id = 0): array
    {
        $avatarCor = trim((string) ($input['avatar_cor'] ?? '#38bdf8'));
        if ($avatarCor === '') {
            $avatarCor = '#38bdf8';
        }

        return [
            'id' => $id > 0 ? $id : (int) ($input['id'] ?? 0),
            'nome' => trim((string) ($input['nome'] ?? '')),
            'usuario' => mb_strtolower(trim((string) ($input['usuario'] ?? ''))),
            'email' => mb_strtolower(trim((string) ($input['email'] ?? ''))),
            'papel' => trim((string) ($input['papel'] ?? 'admin')),
            'status' => trim((string) ($input['status'] ?? 'ativo')),
            'senha' => (string) ($input['senha'] ?? ''),
            'senha_confirmacao' => (string) ($input['senha_confirmacao'] ?? ''),
            'avatar_tipo' => trim((string) ($input['avatar_tipo'] ?? 'icone')),
            'avatar_icone' => trim((string) ($input['avatar_icone'] ?? 'fa-solid fa-user')),
            'avatar_cor' => $avatarCor,
            'avatar_imagem' => trim((string) ($input['avatar_imagem'] ?? '')),
            'avatar_focal_x' => $this->sanitizePercent((float) ($input['avatar_focal_x'] ?? 50.0)),
            'avatar_focal_y' => $this->sanitizePercent((float) ($input['avatar_focal_y'] ?? 50.0)),
            'limpar_avatar_imagem' => (int) ($input['limpar_avatar_imagem'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    private function validateForm(array $form, ?int $ignoreId = null, bool $requirePassword = false, ?int $currentUserId = null): array
    {
        $errors = [];

        if ($form['nome'] === '') {
            $errors['nome'] = 'Informe o nome do usuario.';
        } elseif (mb_strlen($form['nome']) > 120) {
            $errors['nome'] = 'Use ate 120 caracteres no nome.';
        }

        if ($form['usuario'] === '') {
            $errors['usuario'] = 'Informe o login do usuario.';
        } elseif (!preg_match('/^[a-z0-9._-]{3,50}$/', $form['usuario'])) {
            $errors['usuario'] = 'Use de 3 a 50 caracteres com letras, numeros, ponto, traco ou underline.';
        } elseif ($this->usuarios->existsUsuario($form['usuario'], $ignoreId)) {
            $errors['usuario'] = 'Este usuario ja esta em uso.';
        }

        if ($form['email'] === '') {
            $errors['email'] = 'Informe o email do usuario.';
        } elseif (filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Informe um email valido.';
        } elseif ($this->usuarios->existsEmail($form['email'], $ignoreId)) {
            $errors['email'] = 'Este email ja esta em uso.';
        }

        if (!isset(self::PAPEL_OPTIONS[$form['papel']])) {
            $errors['papel'] = 'Selecione um papel valido.';
        }

        if (!isset(self::STATUS_OPTIONS[$form['status']])) {
            $errors['status'] = 'Selecione um status valido.';
        }

        if ($currentUserId !== null && $ignoreId !== null && $ignoreId === $currentUserId && $form['status'] !== 'ativo') {
            $errors['status'] = 'Voce nao pode desativar a propria sessao.';
        }

        if (!in_array($form['avatar_tipo'], ['icone', 'foto'], true)) {
            $errors['avatar_tipo'] = 'Selecione um tipo de avatar valido.';
        }

        if (!isset(self::AVATAR_ICON_OPTIONS[$form['avatar_icone']])) {
            $errors['avatar_icone'] = 'Selecione um icone valido.';
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $form['avatar_cor'])) {
            $errors['avatar_cor'] = 'Use uma cor hexadecimal valida no formato #RRGGBB.';
        }

        if ($requirePassword && trim($form['senha']) === '') {
            $errors['senha'] = 'Informe uma senha para o usuario.';
        }

        if (trim($form['senha']) !== '' && mb_strlen($form['senha']) < 6) {
            $errors['senha'] = 'A senha deve ter ao menos 6 caracteres.';
        }

        if (trim($form['senha']) !== '' && $form['senha'] !== $form['senha_confirmacao']) {
            $errors['senha_confirmacao'] = 'A confirmacao de senha nao confere.';
        }

        return $errors;
    }

    private function applyAvatar(array $form, array $files, ?array $existing, array &$errors): array
    {
        $currentAvatar = trim((string) ($existing['avatar_imagem'] ?? $form['avatar_imagem'] ?? ''));

        if ((int) ($form['limpar_avatar_imagem'] ?? 0) === 1 && $currentAvatar !== '') {
            $this->removeManagedAvatar($currentAvatar);
            $currentAvatar = '';
        }

        $upload = $this->midia->storeUploadedImage(
            $files['avatar_upload'] ?? null,
            'usuarios',
            'usuario-' . $this->slugify($form['usuario'] !== '' ? $form['usuario'] : 'avatar'),
            true
        );

        if (($upload['ok'] ?? false) !== true) {
            $errors['avatar_upload'] = (string) ($upload['error'] ?? 'Falha no upload do avatar.');
        }

        if (($upload['skipped'] ?? true) !== true && !empty($upload['path'])) {
            if ($currentAvatar !== '' && $currentAvatar !== (string) $upload['path']) {
                $this->removeManagedAvatar($currentAvatar);
            }
            $currentAvatar = (string) $upload['path'];
        }

        $form['avatar_imagem'] = $currentAvatar;

        if ($form['avatar_tipo'] === 'foto' && $form['avatar_imagem'] === '') {
            $errors['avatar_upload'] = 'Envie uma foto para usar avatar em foto.';
        }

        return $form;
    }

    private function removeManagedAvatar(string $path): void
    {
        $path = trim($path);
        if ($path === '' || !str_starts_with($path, 'uploads/usuarios/')) {
            return;
        }

        $this->midia->delete($path);
    }

    private function normalizeFilters(array $query): array
    {
        return [
            'busca' => trim((string) ($query['busca'] ?? '')),
            'papel' => trim((string) ($query['papel'] ?? '')),
            'status' => trim((string) ($query['status'] ?? '')),
        ];
    }

    private function normalizeSortDir(string $sort, string $dir): array
    {
        $allowedSort = ['nome', 'usuario', 'email', 'papel', 'status', 'ultimo_acesso', 'criado_em'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'criado_em';
        }

        return [$sort, strtolower(trim($dir)) === 'asc' ? 'asc' : 'desc'];
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($normalized) && $normalized !== '') {
                $value = $normalized;
            }
        }

        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function sanitizePercent(float $value): float
    {
        if (!is_finite($value)) {
            return 50.0;
        }

        return max(0.0, min(100.0, round($value, 2)));
    }

    private function isActiveAdmin(array $usuario): bool
    {
        return (string) ($usuario['papel'] ?? '') === 'admin' && (string) ($usuario['status'] ?? '') === 'ativo';
    }

    private function wouldRemoveLastActiveAdmin(array $current, array $target): bool
    {
        if (!$this->isActiveAdmin($current)) {
            return false;
        }

        $willRemainAdmin = ($target['papel'] ?? '') === 'admin' && ($target['status'] ?? '') === 'ativo';
        if ($willRemainAdmin) {
            return false;
        }

        return $this->usuarios->countActiveAdmins((int) ($current['id'] ?? 0)) <= 0;
    }
}