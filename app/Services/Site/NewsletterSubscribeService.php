<?php
declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\NewsletterRepository;

final class NewsletterSubscribeService
{
    public function __construct(private NewsletterRepository $newsletter)
    {
    }

    public function subscribe(string $email, ?string $name = null, ?string $ip = null): array
    {
        $email = mb_strtolower(trim($email));
        $name = trim((string) $name);
        $ip = trim((string) $ip);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'message' => 'Informe um e-mail valido.'];
        }

        if ($this->newsletter->emailExists($email)) {
            return ['ok' => true, 'message' => 'Cadastro processado com sucesso.'];
        }

        $this->newsletter->subscribe($email, $name !== '' ? $name : null, $ip !== '' ? $ip : null);

        return ['ok' => true, 'message' => 'Cadastro realizado com sucesso.'];
    }
}
