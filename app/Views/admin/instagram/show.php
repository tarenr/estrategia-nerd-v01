<?php
/**
 * @file        app/Views/admin/instagram/show.php
 * @project     Estrategia Nerd
 * @purpose     Tela de detalhes de mídia do Instagram (FEAT-010)
 *
 * Variáveis disponíveis (injetadas pelo InstagramController::show()):
 * @var string                        $title
 * @var array<string,mixed>           $post         Post local
 * @var array<int,array<string,mixed>> $medias       Mídias do post
 * @var array<int,array<string,mixed>> $insights     Insights da Meta (alcance, impressões, salvamentos, interações)
 * @var array<int,array<string,mixed>> $comments     Comentários recentes
 */

declare(strict_types=1);

// TODO (Claude Code): implementar a view conforme especificação FEAT-010:
//   - Visualização ampliada da mídia ($post['permalink'] para abrir no Instagram)
//   - Cards de métricas detalhadas (alcance, impressões, salvamentos, total de interações)
//   - Lista de comentários: username + texto + data
//   - Botão "Editar" → /admin/instagram/posts/{id}/editar
//   - Botão "Voltar" → /admin/instagram
?>

<?php $this->layout('layouts/admin', ['title' => $title ?? 'Detalhes do Post — Instagram']); ?>

<p>Detalhes do Post — view a implementar pelo Claude Code (FEAT-010).</p>
