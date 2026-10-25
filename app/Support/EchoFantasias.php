<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Fantasias do Echo na home (FEAT-016): lista em config/echo-fantasias.php e a
 * escolhida na tabela configuracoes (chave echo_fantasia) de cada ambiente.
 */
final class EchoFantasias
{
    public const CONFIG_KEY = 'echo_fantasia';

    /** @var array{padrao:string,fantasias:array<string,array{label:string,pecas:array<int,string>}>}|null */
    private static ?array $cache = null;

    /**
     * @return array<string, array{label:string,pecas:array<int,string>}>
     */
    public static function all(): array
    {
        return self::load()['fantasias'];
    }

    public static function defaultId(): string
    {
        return self::load()['padrao'];
    }

    public static function exists(string $id): bool
    {
        return isset(self::all()[$id]);
    }

    /** Escolha salva válida, ou o padrão quando não há escolha ou ela não existe mais. */
    public static function resolve(?string $saved): string
    {
        $saved = trim((string) $saved);
        return ($saved !== '' && self::exists($saved)) ? $saved : self::defaultId();
    }

    /**
     * Peças a vestir no site para a fantasia salva no ambiente atual. Qualquer
     * falha devolve as peças do padrão, sem quebrar a home.
     *
     * @return array<int, string>
     */
    public static function piecesForSite(): array
    {
        try {
            $id = self::resolve((string) portal_config(self::CONFIG_KEY, ''));
        } catch (\Throwable $e) {
            error_log('[EchoFantasias] ' . $e->getMessage());
            $id = self::defaultId();
        }

        return self::all()[$id]['pecas'] ?? [];
    }

    /**
     * @return array{padrao:string,fantasias:array<string,array{label:string,pecas:array<int,string>}>}
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $data = is_file(base_path('config/echo-fantasias.php')) ? require base_path('config/echo-fantasias.php') : [];
        $fantasias = [];
        foreach ((array) ($data['fantasias'] ?? []) as $id => $item) {
            if (!is_string($id) || !is_array($item)) {
                continue;
            }
            $fantasias[$id] = [
                'label' => (string) ($item['label'] ?? $id),
                'pecas' => array_values(array_filter(array_map('strval', (array) ($item['pecas'] ?? [])), static fn (string $p): bool => $p !== '')),
            ];
        }
        if ($fantasias === []) {
            $fantasias = ['nenhuma' => ['label' => 'Sem fantasia', 'pecas' => []]];
        }
        $padrao = (string) ($data['padrao'] ?? '');
        if (!isset($fantasias[$padrao])) {
            $padrao = (string) array_key_first($fantasias);
        }

        return self::$cache = ['padrao' => $padrao, 'fantasias' => $fantasias];
    }
}
