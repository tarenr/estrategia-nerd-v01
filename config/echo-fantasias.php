<?php
declare(strict_types=1);

/*
 * Fantasias do Echo na home (FEAT-016). A escolhida fica na tabela configuracoes
 * (chave echo_fantasia) do ambiente; sem escolha, vale "padrao".
 * "pecas" são as chaves de RoboRoupas.ITEMS em
 * public/assets/vendor/echo-robo/robo-roupas-echo.js (só peças que não são de teste).
 */
return [
    'padrao' => 'jack',
    'fantasias' => [
        'nenhuma' => ['label' => 'Sem fantasia', 'pecas' => []],
        'jack' => ['label' => 'Jack Esqueleto', 'pecas' => ['sorrisoCosturado', 'ternoEsqueleto']],
        'kratos' => ['label' => 'Kratos', 'pecas' => ['guerraEspartana', 'armaduraKratos']],
        'tyrael' => ['label' => 'Tyrael', 'pecas' => ['capuzTyrael', 'rostoSombras', 'armaduraTyrael']],
        'vader' => ['label' => 'Darth Vader', 'pecas' => ['capaceteVader', 'mascaraVader', 'armaduraVader']],
        'suporte' => ['label' => 'Suporte de TI', 'pecas' => ['headsetTI', 'moletomNerd']],
        'programador' => ['label' => 'Programador', 'pecas' => ['oculosProgramador', 'moletomNerd']],
        'moletom' => ['label' => 'Moletom Estratégia Nerd', 'pecas' => ['moletomNerd']],
    ],
];
