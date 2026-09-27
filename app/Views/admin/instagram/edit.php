<?php
/**
 * @file        app/Views/admin/instagram/edit.php
 * @project     Estrategia Nerd
 * @purpose     Tela de edição de post do Instagram (FEAT-010)
 *
 * Variáveis disponíveis (injetadas pelo InstagramController::edit()):
 * @var string                        $title
 * @var array<string,mixed>           $post         Post a editar
 * @var array<int,array<string,mixed>> $medias       Mídias atuais do post
 * @var array<int,array<string,mixed>> $blog_posts   Posts publicados do blog
 * @var string                        $csrf_token
 * @var string|null                   $error        Mensagem de erro de validação
 * @var array<string,mixed>           $old          Valores anteriores do formulário
 */

declare(strict_types=1);

// TODO (Claude Code): implementar a view conforme especificação FEAT-010.
//   - Mesma estrutura da view create.php, mas com valores pré-preenchidos de $post e $medias
//   - Form: POST /admin/instagram/posts/{id}/editar com _csrf_token, id=$post['id'],
//     tipo, legenda, acao, agendado_para, post_blog_id, medias[] (substituição opcional)
//   - Exibir mídias atuais com opção de remover/substituir
?>

<?php $this->layout('layouts/admin', ['title' => $title ?? 'Editar Post — Instagram']); ?>

<p>Editar Post no Instagram — view a implementar pelo Claude Code (FEAT-010).</p>
