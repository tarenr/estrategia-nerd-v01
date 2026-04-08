<?php
/**
 * -----------------------------------------------------------------------------
 * @file        config/routes.php
 * @project     Estrategia Nerd
 * @purpose     Definir rotas da aplicacao
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

return [
    ['GET',  '/',       [\App\Controllers\Site\HomeController::class, 'index'], null],
    ['GET',  '/blog',   [\App\Controllers\Site\BlogController::class, 'index'], null],
    ['GET',  '/central-nerd',   [\App\Controllers\Site\CentralController::class, 'index'], null],
    ['GET',  '/link/{slug}', [\App\Controllers\Site\LinkController::class, 'go'], null],
    ['GET',  '/post/{slug}', [\App\Controllers\Site\PostController::class, 'show'], null],
    ['POST', '/post/{slug}/comentarios', [\App\Controllers\Site\PostController::class, 'comment'], null],
    ['POST', '/post/{slug}/curtir', [\App\Controllers\Site\PostController::class, 'like'], null],
    ['GET',  '/politica-de-privacidade', [\App\Controllers\Site\PagesController::class, 'privacy'], null],
    ['GET',  '/termos-de-uso', [\App\Controllers\Site\PagesController::class, 'terms'], null],
    ['POST', '/newsletter', [\App\Controllers\Site\NewsletterController::class, 'subscribe'], null],

    ['GET',  '/login',  [\App\Controllers\Site\AuthController::class, 'showLogin'], null],
    ['POST', '/login',  [\App\Controllers\Site\AuthController::class, 'login'], null],
    ['POST', '/logout', [\App\Controllers\Site\AuthController::class, 'logout'], null],

    ['GET',  '/admin',               [\App\Controllers\Admin\DashboardController::class, 'index'], 'auth'],
    ['GET',  '/admin/api/dashboard', [\App\Controllers\Admin\DashboardController::class, 'data'], 'auth'],

    ['GET',  '/admin/posts',         [\App\Controllers\Admin\PostsController::class, 'index'], 'auth'],
    ['GET',  '/admin/criar-post',    [\App\Controllers\Admin\PostsController::class, 'create'], 'auth'],
    ['POST', '/admin/criar-post',    [\App\Controllers\Admin\PostsController::class, 'store'], 'auth'],
    ['GET',  '/admin/editar-post',   [\App\Controllers\Admin\PostsController::class, 'edit'], 'auth'],
    ['POST', '/admin/editar-post',   [\App\Controllers\Admin\PostsController::class, 'update'], 'auth'],
    ['POST', '/admin/duplicar-post', [\App\Controllers\Admin\PostsController::class, 'duplicate'], 'auth'],
    ['POST', '/admin/upload-post-imagem', [\App\Controllers\Admin\PostsController::class, 'uploadInlineImage'], 'auth'],
    ['POST', '/admin/limpar-post-imagens-orfas', [\App\Controllers\Admin\PostsController::class, 'cleanupOrphanImages'], 'auth'],
    ['GET',  '/admin/excluir-post',  [\App\Controllers\Admin\PostsController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-post',  [\App\Controllers\Admin\PostsController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/categorias',          [\App\Controllers\Admin\CategoriasController::class, 'index'], 'auth'],
    ['GET',  '/admin/criar-categoria',     [\App\Controllers\Admin\CategoriasController::class, 'create'], 'auth'],
    ['POST', '/admin/criar-categoria',     [\App\Controllers\Admin\CategoriasController::class, 'store'], 'auth'],
    ['GET',  '/admin/editar-categoria',    [\App\Controllers\Admin\CategoriasController::class, 'edit'], 'auth'],
    ['POST', '/admin/editar-categoria',    [\App\Controllers\Admin\CategoriasController::class, 'update'], 'auth'],
    ['GET',  '/admin/excluir-categoria',   [\App\Controllers\Admin\CategoriasController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-categoria',   [\App\Controllers\Admin\CategoriasController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/midia',               [\App\Controllers\Admin\MidiaController::class, 'index'], 'auth'],
    ['POST', '/admin/midia/upload',        [\App\Controllers\Admin\MidiaController::class, 'upload'], 'auth'],
    ['POST', '/admin/midia/limpar-orfas',  [\App\Controllers\Admin\MidiaController::class, 'cleanupOrphans'], 'auth'],
    ['GET',  '/admin/excluir-midia',       [\App\Controllers\Admin\MidiaController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-midia',       [\App\Controllers\Admin\MidiaController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/newsletter',          [\App\Controllers\Admin\NewsletterController::class, 'index'], 'auth'],
    ['GET',  '/admin/inscritos',           [\App\Controllers\Admin\NewsletterController::class, 'index'], 'auth'],
    ['POST', '/admin/newsletter/status',   [\App\Controllers\Admin\NewsletterController::class, 'updateStatus'], 'auth'],
    ['GET',  '/admin/excluir-inscrito',    [\App\Controllers\Admin\NewsletterController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-inscrito',    [\App\Controllers\Admin\NewsletterController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/configuracoes',       [\App\Controllers\Admin\ConfiguracoesController::class, 'index'], 'auth'],
    ['POST', '/admin/configuracoes',       [\App\Controllers\Admin\ConfiguracoesController::class, 'update'], 'auth'],
    ['GET',  '/admin/health',              [\App\Controllers\Admin\HealthCheckController::class, 'index'], 'auth'],

    ['GET',  '/admin/links',               [\App\Controllers\Admin\LinksController::class, 'index'], 'auth'],
    ['GET',  '/admin/criar-link',          [\App\Controllers\Admin\LinksController::class, 'create'], 'auth'],
    ['POST', '/admin/criar-link',          [\App\Controllers\Admin\LinksController::class, 'store'], 'auth'],
    ['GET',  '/admin/editar-link',         [\App\Controllers\Admin\LinksController::class, 'edit'], 'auth'],
    ['POST', '/admin/editar-link',         [\App\Controllers\Admin\LinksController::class, 'update'], 'auth'],
    ['POST', '/admin/links/acao',          [\App\Controllers\Admin\LinksController::class, 'quickAction'], 'auth'],
    ['POST', '/admin/links/reordenar',     [\App\Controllers\Admin\LinksController::class, 'reorder'], 'auth'],
    ['GET',  '/admin/excluir-link',        [\App\Controllers\Admin\LinksController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-link',        [\App\Controllers\Admin\LinksController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/comentarios',         [\App\Controllers\Admin\ComentariosController::class, 'index'], 'auth'],
    ['GET',  '/admin/responder-comentario',[\App\Controllers\Admin\ComentariosController::class, 'reply'], 'auth'],
    ['POST', '/admin/responder-comentario',[\App\Controllers\Admin\ComentariosController::class, 'storeReply'], 'auth'],
    ['POST', '/admin/moderar-comentario',  [\App\Controllers\Admin\ComentariosController::class, 'moderate'], 'auth'],
    ['GET',  '/admin/excluir-comentario',  [\App\Controllers\Admin\ComentariosController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-comentario',  [\App\Controllers\Admin\ComentariosController::class, 'destroy'], 'auth'],

    ['GET',  '/dev',                 [\App\Controllers\Site\DevController::class, 'index'], null],
];
