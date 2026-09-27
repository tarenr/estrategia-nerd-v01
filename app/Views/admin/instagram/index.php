<?php
/**
 * @file        app/Views/admin/instagram/index.php
 * @project     Estrategia Nerd
 * @purpose     Dashboard principal do módulo Instagram (FEAT-010)
 *
 * Variáveis disponíveis (injetadas pelo InstagramController::index()):
 * @var string                        $title
 * @var array<string,mixed>|null      $account      Conta ativa ou null se não configurada
 * @var array<int,array<string,mixed>> $scheduled    Posts agendados
 * @var array<int,array<string,mixed>> $drafts       Rascunhos
 * @var array<int,array<string,mixed>> $published    Publicados locais
 * @var array<int,array<string,mixed>> $feed         Feed ao vivo da Meta API
 * @var array<string,mixed>|null      $insights7     Insights 7d (cache)
 * @var array<string,mixed>|null      $insights30    Insights 30d (cache)
 */

declare(strict_types=1);

// TODO (Claude Code): implementar a view completa conforme especificação FEAT-010:
//   - Header do perfil: foto, @username, bio, seguidores (variação), seguindo, mídias
//   - Status da conexão Meta (online/autenticado, última sincronização, botão Sincronizar Agora via POST /admin/instagram/sincronizar)
//   - Cards de métricas dos últimos 7/30d: alcance, visitas ao perfil, impressões, interações
//   - Feed visual: imagem/vídeo, curtidas, comentários, alcance e link externo
//   - Tabela de agendados e rascunhos com ações rápidas
//   - Botão "Novo Post" → /admin/instagram/posts/criar
?>

<?php $this->layout('layouts/admin', ['title' => $title ?? 'Instagram']); ?>

<p>Dashboard do Instagram — view a implementar pelo Claude Code (FEAT-010).</p>
