<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\LinkClickRepository;
use App\Repositories\LinkRepository;
use Throwable;

final class LinksService
{
    public function __construct(
        private LinkRepository $links,
        private LinkClickRepository $linkClicks,
        private MidiaService $midia,
    )
    {
    }

    public function getIndexViewModel(array $query = []): array
    {
        $filters = $this->normalizeFilters($query);
        $page = $this->clampInt((int) ($query['page'] ?? 1), 1, 9999);
        $perPage = $this->clampInt((int) ($query['per_page'] ?? 10), 5, 50);
        [$sort, $dir] = $this->normalizeSortDir((string) ($query['sort'] ?? 'posicao'), (string) ($query['dir'] ?? 'asc'));

        $filteredItems = $this->links->listAdmin($filters, $sort, $dir);
        $filteredItemsWithMetrics = $this->attachClickMetrics($filteredItems);
        $pagination = $this->links->paginateAdmin($filters, $page, $perPage, $sort, $dir);
        $pagination['items'] = $this->attachClickMetrics(is_array($pagination['items'] ?? null) ? $pagination['items'] : []);

        $total = count($filteredItemsWithMetrics);
        $ativos = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'ativo'));
        $ocultos = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'oculto'));
        $revisar = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'quebrado'));
        $destaques = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (int) ($item['destaque'] ?? 0) === 1));

        $promocoes = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'produto' && (int) ($item['promocao'] ?? 0) === 1));
        $produtosCatalogo = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'produto' && (int) ($item['promocao'] ?? 0) === 0));
        $produtosTotal = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'produto'));
        $gruposProdutos = $this->countDistinctNonEmpty(array_map(
            static fn (array $item): string => (string) ($item['tipo'] ?? '') === 'produto' ? (string) ($item['subgrupo_publico'] ?? '') : '',
            $filteredItemsWithMetrics
        ));

        $cupons = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'cupom'));
        $cuponsComCodigo = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'cupom' && trim((string) ($item['codigo_cupom'] ?? '')) !== ''));
        $cuponsComContexto = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'cupom' && trim((string) ($item['desconto_contexto'] ?? '')) !== ''));

        $conteudo = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'conteudo'));
        $redes = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'rede_social'));
        $servicos = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'servico'));
        $canaisTotal = $conteudo + $redes + $servicos;

        $clickTotal = array_sum(array_map(static fn (array $item): int => (int) ($item['click_total'] ?? 0), $filteredItemsWithMetrics));
        $clickToday = array_sum(array_map(static fn (array $item): int => (int) ($item['click_today'] ?? 0), $filteredItemsWithMetrics));
        $linksComClique = count(array_filter($filteredItemsWithMetrics, static fn (array $item): bool => (int) ($item['click_total'] ?? 0) > 0));
        $avgClicks = $linksComClique > 0 ? round($clickTotal / $linksComClique, 1) : 0.0;

        return [
            'title' => 'Links',
            'items' => $pagination['items'] ?? [],
            'current_featured' => $this->links->findFeaturedProduct(),
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'pagination' => $pagination,
            'summary' => [
                'total' => $total,
                'ativos' => $ativos,
                'ocultos' => $ocultos,
                'revisar' => $revisar,
                'destaques' => $destaques,
                'promocoes' => $promocoes,
                'produtos_catalogo' => $produtosCatalogo,
                'produtos_total' => $produtosTotal,
                'grupos_produtos' => $gruposProdutos,
                'cupons' => $cupons,
                'cupons_codigo' => $cuponsComCodigo,
                'cupons_contexto' => $cuponsComContexto,
                'conteudo' => $conteudo,
                'redes' => $redes,
                'servicos' => $servicos,
                'canais_total' => $canaisTotal,
                'click_total' => $clickTotal,
                'click_today' => $clickToday,
                'links_com_clique' => $linksComClique,
                'click_avg' => $avgClicks,
            ],
        ];
    }

    public function getCreateViewModel(array $old = [], array $errors = []): array
    {
        $form = $this->normalizeForm($old);
        return $this->buildFormViewModel('create', $form, $errors);
    }

    public function getEditViewModel(int $id, array $old = [], array $errors = []): ?array
    {
        $link = $this->links->findById($id);
        if ($link === null) {
            return null;
        }

        $form = $old !== []
            ? array_replace($this->mapLinkToForm($link), $this->normalizeForm($old, $id))
            : $this->mapLinkToForm($link);

        return $this->buildFormViewModel('edit', $form, $errors, $link);
    }

    public function getDeleteViewModel(int $id): ?array
    {
        $link = $this->links->findById($id);
        if ($link === null) {
            return null;
        }

        return [
            'title' => 'Excluir Link',
            'link' => $link,
        ];
    }

    public function createLink(array $input): array
    {
        $form = $this->normalizeForm($input);
        $errors = $this->validateForm($form);

        if ($errors === []) {
            $slugPreview = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
            $this->applyImageUpload($form, $_FILES ?? [], $errors, $slugPreview);
        }

        if ($errors !== []) {
            return ['ok' => false, 'viewModel' => $this->buildFormViewModel('create', $form, $errors)];
        }

        $slugBase = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
        $slug = $this->links->nextAvailableSlug($slugBase);
        $id = $this->links->insertAdmin($this->payloadFromForm($form, $slug));
        $this->enforceSingleFeaturedProduct($form, $id);

        return ['ok' => true, 'slug' => $slug];
    }

    public function updateLink(int $id, array $input): array
    {
        $link = $this->links->findById($id);
        if ($link === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $form = $this->normalizeForm($input, $id);
        $errors = $this->validateForm($form, $id);

        if ($errors === []) {
            $slugPreview = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
            $this->applyImageUpload($form, $_FILES ?? [], $errors, $slugPreview, $link);
        }

        if ($errors !== []) {
            return ['ok' => false, 'viewModel' => $this->buildFormViewModel('edit', $form, $errors, $link)];
        }

        $slugBase = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
        $slug = $this->links->nextAvailableSlug($slugBase, $id);
        $this->links->updateAdmin($id, $this->payloadFromForm($form, $slug));
        $this->enforceSingleFeaturedProduct($form, $id);

        return ['ok' => true, 'id' => $id, 'slug' => $slug];
    }

    public function deleteLink(int $id): array
    {
        $link = $this->links->findById($id);
        if ($link === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $this->links->deleteById($id);
        return ['ok' => true];
    }

    public function quickAction(int $id, string $action): array
    {
        $link = $this->links->findById($id);
        if ($link === null) {
            return ['ok' => false, 'not_found' => true];
        }

        return match ($action) {
            'toggle_status' => $this->toggleStatus($link),
            'toggle_destaque' => $this->toggleDestaque($link),
            'move_up' => $this->moveLink($link, 'up'),
            'move_down' => $this->moveLink($link, 'down'),
            'check_link' => $this->checkLink($link),
            default => ['ok' => false, 'invalid_action' => true],
        };
    }

    public function reorderLinks(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return ['ok' => false, 'invalid_action' => true];
        }

        $existing = $this->links->listAdmin([], 'posicao', 'asc');
        $existingIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $existing);
        $orderedIds = [];

        foreach ($ids as $id) {
            if (in_array($id, $existingIds, true)) {
                $orderedIds[] = $id;
            }
        }

        foreach ($existingIds as $id) {
            if (!in_array($id, $orderedIds, true)) {
                $orderedIds[] = $id;
            }
        }

        $this->links->reorderPositions($orderedIds);
        return ['ok' => true, 'mode' => 'order_drag'];
    }

    /**
     * @param array<int, array<string,mixed>> $items
     * @return array<int, array<string,mixed>>
     */
    private function attachClickMetrics(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $ids = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $items);
        $metrics = $this->linkClicks->metricsByLinkIds($ids);

        foreach ($items as $index => $item) {
            $id = (int) ($item['id'] ?? 0);
            $metric = $metrics[$id] ?? ['total_clicks' => 0, 'clicks_today' => 0, 'last_click_at' => ''];
            $items[$index]['click_total'] = (int) ($metric['total_clicks'] ?? 0);
            $items[$index]['click_today'] = (int) ($metric['clicks_today'] ?? 0);
            $items[$index]['last_click_at'] = (string) ($metric['last_click_at'] ?? '');
        }

        return $items;
    }


    private function countDistinctNonEmpty(array $values): int
    {
        $clean = [];
        foreach ($values as $value) {
            $normalized = trim((string) $value);
            if ($normalized === '') {
                continue;
            }
            $clean[mb_strtolower($normalized)] = true;
        }

        return count($clean);
    }
    private function buildFormViewModel(string $mode, array $form, array $errors = [], ?array $link = null): array
    {
        return [
            'title' => $mode === 'edit' ? 'Editar Link' : 'Criar Link',
            'mode' => $mode,
            'form' => $form,
            'errors' => $errors,
            'link' => $link,
            'current_featured' => $this->links->findFeaturedProduct(),
            'media_items' => $this->midia->recentImages(12),
        ];
    }

    private function payloadFromForm(array $form, string $slug): array
    {
        return [
            'titulo' => $form['titulo'],
            'slug' => $slug,
            'url' => $form['url'],
            'tipo' => $form['tipo'],
            'promocao' => $form['promocao'],
            'desconto_percentual' => $form['desconto_percentual'],
            'desconto_contexto' => $form['desconto_contexto'],
            'codigo_cupom' => $form['codigo_cupom'],
            'secao_publica' => $this->derivePublicSection($form['tipo'], $form['promocao']),
            'subgrupo_publico' => $form['subgrupo_publico'],
            'descricao' => $form['descricao'],
            'cta_curto' => $form['cta_curto'],
            'texto_botao' => $form['texto_botao'],
            'selo' => $form['selo'],
            'imagem' => $form['imagem'],
            'posicao' => $form['posicao'],
            'status' => $form['status'],
            'destaque' => $form['destaque'],
            'expira_em' => $form['expira_em'],
            'observacao_status' => $form['observacao_status'],
        ];
    }

    private function derivePublicSection(string $tipo, int $promocao): string
    {
        if ($tipo === 'produto' && $promocao === 1) {
            return 'promocoes';
        }

        return match ($tipo) {
            'produto' => 'produtos',
            'cupom' => 'cupons',
            'conteudo' => 'conteudo',
            'rede_social' => 'rede_social',
            'servico' => 'servicos',
            default => 'produtos',
        };
    }

    private function toggleStatus(array $link): array
    {
        $current = (string) ($link['status'] ?? 'ativo');
        $next = $current === 'oculto' ? 'ativo' : 'oculto';
        $this->links->updateQuickFields((int) ($link['id'] ?? 0), ['status' => $next]);

        return ['ok' => true, 'mode' => 'status_' . $next];
    }

    private function toggleDestaque(array $link): array
    {
        $id = (int) ($link['id'] ?? 0);
        $current = (int) ($link['destaque'] ?? 0) === 1 ? 1 : 0;
        $next = $current === 1 ? 0 : 1;
        if ($next === 1) {
            $this->links->clearFeaturedProducts($id);
        }
        $this->links->updateQuickFields($id, ['destaque' => $next]);

        return ['ok' => true, 'mode' => 'destaque_' . ($next === 1 ? 'on' : 'off')];
    }

    private function moveLink(array $link, string $direction): array
    {
        $items = $this->links->listAdmin([], 'posicao', 'asc');
        $currentId = (int) ($link['id'] ?? 0);
        $currentIndex = null;

        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) === $currentId) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
        if (!isset($items[$targetIndex])) {
            return ['ok' => true, 'mode' => 'order_unchanged'];
        }

        $currentPosition = (int) ($items[$currentIndex]['posicao'] ?? 0);
        $targetPosition = (int) ($items[$targetIndex]['posicao'] ?? 0);
        $targetId = (int) ($items[$targetIndex]['id'] ?? 0);

        $this->links->updatePositionById($currentId, $targetPosition);
        $this->links->updatePositionById($targetId, $currentPosition);

        return ['ok' => true, 'mode' => $direction === 'up' ? 'order_up' : 'order_down'];
    }

    private function applyImageUpload(array &$form, array $files, array &$errors, string $slug, ?array $existingLink = null): void
    {
        $slug = $slug !== '' ? $slug : 'link';
        $result = $this->midia->storeUploadedImage($files['imagem_upload'] ?? null, 'links', $slug . '-cover', true);
        if (($result['ok'] ?? false) !== true) {
            $errors['imagem'] = (string) ($result['error'] ?? 'Falha no upload da imagem do link.');
            return;
        }

        if (($result['skipped'] ?? false) === true) {
            return;
        }

        $newPath = trim((string) ($result['path'] ?? ''));
        if ($newPath === '') {
            return;
        }

        $oldPath = trim((string) ($form['imagem'] ?? ''));
        $form['imagem'] = $newPath;

        if ($existingLink !== null) {
            $fallbackOld = trim((string) ($existingLink['imagem'] ?? ''));
            if ($oldPath === '') {
                $oldPath = $fallbackOld;
            }
        }

        if ($oldPath !== '' && $oldPath !== $newPath) {
            $resolved = $this->resolveUploadFileForDelete($oldPath);
            if ($resolved !== null && is_file($resolved)) {
                @unlink($resolved);
            }
        }
    }

    private function mapLinkToForm(array $link): array
    {
        return [
            'id' => (int) ($link['id'] ?? 0),
            'titulo' => trim((string) ($link['titulo'] ?? '')),
            'slug' => trim((string) ($link['slug'] ?? '')),
            'url' => trim((string) ($link['url'] ?? '')),
            'tipo' => trim((string) ($link['tipo'] ?? 'produto')),
            'promocao' => (int) ($link['promocao'] ?? 0) === 1 ? 1 : 0,
            'subgrupo_publico' => trim((string) ($link['subgrupo_publico'] ?? '')),
            'desconto_percentual' => trim((string) ($link['desconto_percentual'] ?? '')),
            'desconto_contexto' => trim((string) ($link['desconto_contexto'] ?? '')),
            'codigo_cupom' => trim((string) ($link['codigo_cupom'] ?? '')),
            'descricao' => trim((string) ($link['descricao'] ?? '')),
            'cta_curto' => trim((string) ($link['cta_curto'] ?? '')),
            'texto_botao' => trim((string) ($link['texto_botao'] ?? '')),
            'selo' => trim((string) ($link['selo'] ?? '')),
            'imagem' => trim((string) ($link['imagem'] ?? '')),
            'posicao' => (int) ($link['posicao'] ?? 0),
            'status' => trim((string) ($link['status'] ?? 'ativo')),
            'destaque' => (int) ($link['destaque'] ?? 0) === 1 ? 1 : 0,
            'expira_em' => $this->toDateTimeLocal((string) ($link['expira_em'] ?? '')),
            'observacao_status' => trim((string) ($link['observacao_status'] ?? '')),
        ];
    }

    private function normalizeForm(array $input, int $id = 0): array
    {
        $tipo = trim((string) ($input['tipo'] ?? 'produto'));
        if (!in_array($tipo, ['produto', 'cupom', 'conteudo', 'rede_social', 'servico'], true)) {
            $tipo = 'produto';
        }

        $status = trim((string) ($input['status'] ?? 'ativo'));
        if (!in_array($status, ['ativo', 'oculto', 'expirado', 'quebrado'], true)) {
            $status = 'ativo';
        }

        $promocao = $tipo === 'produto' && (int) ($input['promocao'] ?? 0) === 1 ? 1 : 0;
        $subgrupoPublico = trim((string) ($input['subgrupo_publico'] ?? ''));
        if ($tipo !== 'produto') {
            $subgrupoPublico = '';
        }

        $descontoPercentual = trim((string) ($input['desconto_percentual'] ?? ''));
        $descontoContexto = trim((string) ($input['desconto_contexto'] ?? ''));
        $codigoCupom = trim((string) ($input['codigo_cupom'] ?? ''));
        if ($tipo !== 'cupom') {
            $descontoPercentual = '';
            $descontoContexto = '';
            $codigoCupom = '';
        }

        $destaque = (int) ($input['destaque'] ?? 0) === 1 ? 1 : 0;

        return [
            'id' => $id > 0 ? $id : (int) ($input['id'] ?? 0),
            'titulo' => trim((string) ($input['titulo'] ?? '')),
            'slug' => trim((string) ($input['slug'] ?? '')),
            'url' => trim((string) ($input['url'] ?? '')),
            'tipo' => $tipo,
            'promocao' => $promocao,
            'subgrupo_publico' => $subgrupoPublico,
            'desconto_percentual' => $descontoPercentual,
            'desconto_contexto' => $descontoContexto,
            'codigo_cupom' => $codigoCupom,
            'descricao' => trim((string) ($input['descricao'] ?? '')),
            'cta_curto' => trim((string) ($input['cta_curto'] ?? '')),
            'texto_botao' => trim((string) ($input['texto_botao'] ?? '')),
            'selo' => trim((string) ($input['selo'] ?? '')),
            'imagem' => trim((string) ($input['imagem'] ?? '')),
            'posicao' => max(0, (int) ($input['posicao'] ?? 0)),
            'status' => $status,
            'destaque' => $destaque,
            'expira_em' => $this->normalizeDateTime((string) ($input['expira_em'] ?? '')),
            'observacao_status' => trim((string) ($input['observacao_status'] ?? '')),
        ];
    }

    private function validateForm(array $form, ?int $ignoreId = null): array
    {
        $errors = [];

        if ($form['titulo'] === '') {
            $errors['titulo'] = 'Informe o titulo do link.';
        } elseif (mb_strlen($form['titulo']) > 150) {
            $errors['titulo'] = 'O titulo deve ter no maximo 150 caracteres.';
        }

        $slugBase = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
        if ($slugBase === '') {
            $errors['slug'] = 'Nao foi possivel gerar um slug valido para o link.';
        }

        if ($form['url'] === '') {
            $errors['url'] = 'Informe a URL de destino.';
        } elseif (!$this->isValidUrl($form['url'])) {
            $errors['url'] = 'Informe uma URL valida. Use http(s):// ou um caminho interno iniciando com /.';
        }

        if ($form['tipo'] === 'produto' && $form['subgrupo_publico'] === '') {
            $errors['subgrupo_publico'] = 'Informe o grupo de produto que sera exibido na Central Nerd.';
        }

        if ($form['subgrupo_publico'] !== '' && mb_strlen($form['subgrupo_publico']) > 80) {
            $errors['subgrupo_publico'] = 'O grupo de produto deve ter no maximo 80 caracteres.';
        }

        if ($form['tipo'] === 'cupom' && $form['desconto_percentual'] === '') {
            $errors['desconto_percentual'] = 'Informe o valor do desconto.';
        }

        if ($form['desconto_percentual'] !== '' && mb_strlen($form['desconto_percentual']) > 20) {
            $errors['desconto_percentual'] = 'O desconto deve ter no maximo 20 caracteres.';
        }

        if ($form['tipo'] === 'cupom' && $form['desconto_contexto'] === '') {
            $errors['desconto_contexto'] = 'Explique onde o cupom se aplica.';
        }

        if ($form['desconto_contexto'] !== '' && mb_strlen($form['desconto_contexto']) > 160) {
            $errors['desconto_contexto'] = 'A descricao do cupom deve ter no maximo 160 caracteres.';
        }

        if ($form['codigo_cupom'] !== '' && mb_strlen($form['codigo_cupom']) > 80) {
            $errors['codigo_cupom'] = 'O codigo do cupom deve ter no maximo 80 caracteres.';
        }

        if ($form['descricao'] !== '' && mb_strlen($form['descricao']) > 255) {
            $errors['descricao'] = 'A descricao deve ter no maximo 255 caracteres.';
        }

        if ($form['cta_curto'] !== '' && mb_strlen($form['cta_curto']) > 120) {
            $errors['cta_curto'] = 'O CTA curto deve ter no maximo 120 caracteres.';
        }

        if ($form['texto_botao'] !== '' && mb_strlen($form['texto_botao']) > 80) {
            $errors['texto_botao'] = 'O texto do botao deve ter no maximo 80 caracteres.';
        }

        if ($form['selo'] !== '' && mb_strlen($form['selo']) > 60) {
            $errors['selo'] = 'O selo deve ter no maximo 60 caracteres.';
        }

        if ($form['imagem'] !== '' && mb_strlen($form['imagem']) > 255) {
            $errors['imagem'] = 'O caminho da imagem deve ter no maximo 255 caracteres.';
        }

        if ($form['observacao_status'] !== '' && mb_strlen($form['observacao_status']) > 255) {
            $errors['observacao_status'] = 'A observacao deve ter no maximo 255 caracteres.';
        }

        return $errors;
    }

    private function enforceSingleFeaturedProduct(array $form, int $id): void
    {
        if ((int) ($form['destaque'] ?? 0) !== 1) {
            return;
        }

        if ($id <= 0) {
            return;
        }

        $this->links->clearFeaturedProducts($id);
    }

    private function normalizeFilters(array $query): array
    {
        return [
            'busca' => trim((string) ($query['busca'] ?? '')),
            'tipo' => trim((string) ($query['tipo'] ?? '')),
            'promocao' => trim((string) ($query['promocao'] ?? '')),
            'status' => trim((string) ($query['status'] ?? '')),
            'destaque' => trim((string) ($query['destaque'] ?? '')),
            'monitoramento' => trim((string) ($query['monitoramento'] ?? '')),
        ];
    }

    private function normalizeSortDir(string $sort, string $dir): array
    {
        $allowedSort = ['titulo', 'tipo', 'status', 'posicao', 'expira_em', 'updated_at'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'posicao';
        }

        return [$sort, strtolower(trim($dir)) === 'desc' ? 'desc' : 'asc'];
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
        return trim(mb_substr($value, 0, 150), '-');
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function toDateTimeLocal(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d\TH:i', $timestamp);
    }

    private function isValidUrl(string $value): bool
    {
        if (str_starts_with($value, '/')) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function resolveUploadFileForDelete(string $path): ?string
    {
        $raw = trim($path);
        if ($raw === '') {
            return null;
        }

        $parsed = parse_url($raw);
        if (is_array($parsed) && isset($parsed['path']) && is_string($parsed['path'])) {
            $raw = $parsed['path'];
        }

        $raw = ltrim($raw, '/\\');
        if ($raw === '' || !str_starts_with($raw, 'uploads/')) {
            return null;
        }

        $publicRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';
        $uploadsDir = $publicRoot . DIRECTORY_SEPARATOR . 'uploads';
        $target = $publicRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);

        $uploadsReal = realpath($uploadsDir);
        if ($uploadsReal === false) {
            return null;
        }

        $targetReal = realpath($target);
        if ($targetReal !== false) {
            return str_starts_with($targetReal, $uploadsReal) && is_file($targetReal) ? $targetReal : null;
        }

        $candidateDir = realpath(dirname($target));
        if ($candidateDir === false || !str_starts_with($candidateDir, $uploadsReal)) {
            return null;
        }

        return is_file($target) ? $target : null;
    }

    private function checkLink(array $link): array
    {
        $url = trim((string) ($link['url'] ?? ''));
        if ($url === '') {
            return ['ok' => false, 'invalid_action' => true];
        }

        $targetUrl = $this->resolveCheckUrl($url);
        $result = $this->performHttpCheck($targetUrl);

        $currentStatus = (string) ($link['status'] ?? 'ativo');
        $nextStatus = $currentStatus;
        if ($currentStatus !== 'oculto' && $currentStatus !== 'expirado') {
            $nextStatus = ($result['ok'] ?? false) === true ? 'ativo' : 'quebrado';
        }

        $this->links->updateMonitoringById((int) ($link['id'] ?? 0), [
            'status' => $nextStatus,
            'ultima_verificacao' => date('Y-m-d H:i:s'),
            'codigo_http' => $result['codigo_http'] ?? null,
            'url_final' => $result['url_final'] ?? $targetUrl,
            'observacao_status' => $result['observacao_status'] ?? null,
        ]);

        return ['ok' => true, 'mode' => ($result['ok'] ?? false) === true ? 'checked_ok' : 'checked_fail'];
    }

    private function resolveCheckUrl(string $url): string
    {
        if (str_starts_with($url, '/')) {
            return rtrim(url('/'), '/') . $url;
        }

        return $url;
    }

    private function performHttpCheck(string $url): array
    {
        $default = [
            'ok' => false,
            'codigo_http' => null,
            'url_final' => $url,
            'observacao_status' => 'Falha ao verificar o link.',
        ];

        if (function_exists('curl_init')) {
            try {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_NOBODY => true,
                    CURLOPT_TIMEOUT => 12,
                    CURLOPT_CONNECTTIMEOUT => 6,
                    CURLOPT_USERAGENT => 'EstrategiaNerdBot/1.0',
                ]);
                curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error !== '') {
                    return $default + ['observacao_status' => $error];
                }

                return [
                    'ok' => $code >= 200 && $code < 400,
                    'codigo_http' => $code > 0 ? $code : null,
                    'url_final' => $finalUrl !== '' ? $finalUrl : $url,
                    'observacao_status' => $code >= 200 && $code < 400 ? 'Link verificado com sucesso.' : 'Resposta HTTP ' . ($code > 0 ? (string) $code : 'indefinida') . '.',
                ];
            } catch (Throwable) {
                return $default;
            }
        }

        try {
            $headers = @get_headers($url, true);
            if (!is_array($headers) || $headers === []) {
                return $default + ['observacao_status' => 'Sem resposta do destino.'];
            }

            $first = is_array($headers[0] ?? null) ? end($headers[0]) : ($headers[0] ?? '');
            preg_match('/\s(\d{3})\s/', (string) $first, $matches);
            $code = isset($matches[1]) ? (int) $matches[1] : null;

            return [
                'ok' => $code !== null && $code >= 200 && $code < 400,
                'codigo_http' => $code,
                'url_final' => $url,
                'observacao_status' => $code !== null ? 'Resposta HTTP ' . $code . '.' : 'Resposta recebida sem codigo identificavel.',
            ];
        } catch (Throwable) {
            return $default;
        }
    }
}
