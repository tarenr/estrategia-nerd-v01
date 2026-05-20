<?php

declare(strict_types=1);

$module = is_array($module ?? null) ? $module : [];
$searchConsoleTools = is_array($search_console_tools ?? null) ? $search_console_tools : [];
?>

<section class="space-y-6 operations-v2-page">
  <?= \App\Support\View::fragment('site/search-console-monitor', $searchConsoleTools) ?>
</section>
