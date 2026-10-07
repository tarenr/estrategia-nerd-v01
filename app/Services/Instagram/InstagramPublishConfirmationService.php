<?php
declare(strict_types=1);
namespace App\Services\Instagram;

use App\Repositories\InstagramPostRepository;
use RuntimeException;
use Throwable;

/** Shared final publication step. An uncertain POST is never automatically sent again. */
final class InstagramPublishConfirmationService
{
    private readonly string $receiptDirectory;

    public function __construct(
        private readonly InstagramPostRepository $repo,
        private readonly InstagramApiService $api,
        ?string $receiptDirectory = null,
    ) {
        $this->receiptDirectory = $receiptDirectory ?? base_path('storage/backups/instagram-publish/receipts');
    }

    /** @return array{state:string,media_id:string} */
    public function publish(int $postId, string $creationId): array
    {
        $post = $this->repo->findById($postId);
        if ($post === null || !ctype_digit($creationId) || strlen($creationId) > 50) { throw new RuntimeException('Post/container inválido.'); }
        if ($post['status'] === 'publicado') { return ['state'=>'published','media_id'=>(string) ($post['ig_media_id'] ?? '')]; }
        if (!is_dir($this->receiptDirectory) && !mkdir($this->receiptDirectory, 0755, true) && !is_dir($this->receiptDirectory)) {
            throw new RuntimeException('Não foi possível preparar o registro da resposta de publicação.');
        }
        clearstatcache(true, $this->receiptDirectory);
        if (!is_writable($this->receiptDirectory)) { throw new RuntimeException('Pasta de comprovantes indisponível; nada enviado.'); }
        // Compare-and-set is durable before any outgoing request, shared by panel and CLI.
        if (!$this->repo->beginPublishAttempt($postId, $creationId)) { return ['state'=>'pending','media_id'=>'']; }
        try {
            $mediaId = $this->api->publishMedia($creationId);
        } catch (InstagramPublishOutcomeUnknownException $e) {
            $this->repo->markPublishPending($postId, 'Aguardando confirmação da publicação. ' . $e->getMessage());
            return ['state'=>'pending','media_id'=>''];
        } catch (RuntimeException $e) {
            $this->repo->rejectPublishAttempt($postId, $creationId, $e->getMessage());
            return ['state'=>'failed','media_id'=>''];
        } catch (Throwable) {
            $this->repo->markPublishPending($postId, 'Publicação enviada; resultado desconhecido. Não reenviar.');
            return ['state'=>'pending','media_id'=>''];
        }
        try {
            $this->saveReceipt($postId, (int) $post['account_id'], $creationId, $mediaId);
            if (!$this->repo->confirmPublishAttempt($postId, $creationId, $mediaId)) { throw new RuntimeException('Estado da tentativa mudou.'); }
        } catch (Throwable) {
            // ID is public metadata. Log it for recovery without logging response bodies/tokens.
            error_log('[Instagram publish] Confirmação local pendente: post ' . $postId . ', container ' . $creationId . ', mídia ' . $mediaId);
            $this->repo->markPublishPending($postId, 'A Meta retornou a mídia ' . $mediaId . '; confirmação local pendente. Não reenviar.');
            return ['state'=>'pending','media_id'=>$mediaId];
        }
        $this->fillPermalink($postId, $mediaId);
        return ['state'=>'published','media_id'=>$mediaId];
    }

    public function reconcile(int $postId): void
    {
        $post = $this->repo->findById($postId);
        if ($post === null) { return; }
        $mediaId = (string) ($post['ig_media_id'] ?? '');
        if ($post['status'] === 'publicado' && $mediaId !== '') { $this->fillPermalink($postId, $mediaId); return; }
        if ($post['status'] !== 'publicando' || !in_array($post['publish_phase'], ['awaiting_confirmation','published_id_pending'], true)) { return; }
        $creationId = (string) ($post['creation_id'] ?? '');
        if (!ctype_digit($creationId)) { $this->repo->markPublishPending($postId, 'Container não disponível para confirmação. Não reenviar.'); return; }
        $receipt = $this->findReceipt($postId, (int) $post['account_id'], $creationId);
        if ($receipt !== null) {
            if ($this->repo->confirmPublishAttempt($postId, $creationId, $receipt)) { $this->fillPermalink($postId, $receipt); }
            return;
        }
        try { $status = $this->api->checkContainerStatus($creationId); }
        catch (Throwable) {
            $this->repo->markPublishPending($postId, 'Não foi possível consultar a Meta. A publicação continua aguardando confirmação; não reenviar.');
            return;
        }
        $published = $status === 'PUBLISHED';
        $message = $published
            ? 'A Meta indica container publicado, mas o ID da mídia não foi recuperado. Confira no Instagram; não reenviar.'
            : 'A publicação ainda não foi confirmada com segurança. Confira no Instagram antes de uma nova tentativa.';
        // FINISHED is processing readiness, not a published-media ID. No caption/time/ID-diff guessing.
        $this->repo->markPublishPending($postId, $message, $published);
    }

    private function fillPermalink(int $postId, string $mediaId): void
    {
        try {
            $detail = $this->api->getMediaDetails($mediaId);
            if ((string) ($detail['id'] ?? '') !== $mediaId) { throw new RuntimeException('Detalhe de outra mídia.'); }
            $link = (string) ($detail['permalink'] ?? '');
            $host = parse_url($link, PHP_URL_HOST);
            if ($link !== '' && parse_url($link, PHP_URL_SCHEME) === 'https' && in_array($host, ['instagram.com','www.instagram.com'], true)) {
                $this->repo->updatePublishedPermalink($postId, $mediaId, $link);
            }
        } catch (Throwable) {
            error_log('[Instagram publish] Permalink pendente para post ' . $postId . '; publicação confirmada preservada.');
        }
    }

    private function saveReceipt(int $postId, int $accountId, string $creationId, string $mediaId): void
    {
        $publication = ['post_id'=>$postId,'account_id'=>$accountId,'creation_id'=>$creationId,'media_id'=>$mediaId,'received_at'=>date('c')];
        $raw = json_encode($publication, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $content = json_encode(['publication'=>$publication,'sha256'=>hash('sha256',$raw)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $path = $this->receiptDirectory . '/receipt-' . $postId . '-' . $creationId . '-' . bin2hex(random_bytes(6)) . '.json';
        $handle = fopen($path, 'x');
        if ($handle === false) { throw new RuntimeException('Comprovante não gravado.'); }
        try {
            if (fwrite($handle, $content) !== strlen($content) || !fflush($handle) || !fsync($handle)) { throw new RuntimeException('Comprovante incompleto.'); }
        } finally { fclose($handle); }
        if (hash_file('sha256', $path) !== hash('sha256', $content)) { throw new RuntimeException('Comprovante não íntegro.'); }
    }

    private function findReceipt(int $postId, int $accountId, string $creationId): ?string
    {
        foreach (glob($this->receiptDirectory . '/receipt-' . $postId . '-' . $creationId . '-*.json') ?: [] as $path) {
            try {
                $receipt = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($receipt) || !is_array($receipt['publication'] ?? null) || !is_string($receipt['sha256'] ?? null)) { continue; }
                $p = $receipt['publication'];
                if (!hash_equals($receipt['sha256'], hash('sha256', json_encode($p, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)))
                    || ($p['post_id'] ?? null) !== $postId || ($p['account_id'] ?? null) !== $accountId
                    || ($p['creation_id'] ?? '') !== $creationId || !is_string($p['media_id'] ?? null)
                    || !ctype_digit($p['media_id']) || $p['media_id'] === $creationId) { continue; }
                return $p['media_id'];
            } catch (Throwable) { continue; }
        }
        return null;
    }
}
