<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kamu - Error</title>
    <style>
        body {
            margin: 2rem 1rem;
            font-family: monospace;
            font-size: 0.94rem;
        }

        .pre {
            font-size: 1.4rem;
            font-weight: bold;
            overflow: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        th {
            background-color: #aaaaaa;
        }

        td {
            text-align: left;
            height: 1.55rem;
            border-bottom: 0.05rem solid #bbb;
            word-wrap: break-word;
        }

        tr:hover {
            background-color: #cccccc;
        }

        .main-line {
            background-color: pink;
        }

        .trace {
            display: flex;
            flex-direction: row;
            height: 50vh;
        }

        @media (max-width: 800px) {
            .trace {
                flex-direction: column;
            }
        }

        .information {
            flex: 40%;
            overflow: auto;
            word-wrap: break-word;
            cursor: pointer;
            border: 0.125rem solid #bbb;
        }

        .item {
            padding: 0.4rem;
            text-align: left;
            border-bottom: 0.05rem solid #bbb;
        }

        .item:hover {
            background-color: #aaaaaa;
        }

        .file {
            flex: 60%;
            overflow: auto;
            word-wrap: break-word;
            border: 0.125rem solid #bbb;
        }

        .black>span {
            color: black !important;
        }
    </style>
</head>

<?php

$depthReadFile = 6;

ini_set("highlight.comment", "#008000");
ini_set("highlight.default", "#000000");
ini_set("highlight.html", "#808080");
ini_set("highlight.keyword", "#0000BB");
ini_set("highlight.string", "#DD0000");

?>

<body>
    <header>
        <h1 style="font-family: monospace; word-wrap: break-word;"><?= $error::class ?></h1>
        <hr>
        <pre class="pre"><?= htmlspecialchars($error->getMessage()) ?></pre>
    </header>

    <nav>
        <?php if ($error instanceof \Core\Database\Exception\DatabaseException) : ?>
            <h3 style="font-family: monospace; overflow: auto; white-space: pre-wrap; word-wrap: break-word;">Query: <?= htmlspecialchars($error->getQueryString() ?? 'null') ?></h3>
        <?php endif ?>

        <h3 style="font-family: monospace; overflow: auto; white-space: pre-wrap; word-wrap: break-word;">Process: <?= execute_time() ?>ms</h3>
    </nav>

    <main>

        <section class="trace">
            <div class="information">
                <div class="item" onclick="document.getElementById('file').innerHTML = document.getElementById(0).innerHTML">
                    <?php
                    if (!empty($error->getFile())) {

                        echo '<div id="0" style="display: none">';
                        echo '<pre style="margin: 0.25rem;">' . htmlspecialchars($error->getFile()) . '</pre><hr>';
                        $depth = $depthReadFile;
                        $filename = @highlight_file($error->getFile(), true);
                        $file = explode(str_contains($filename, '<br />') ? '<br />' : "\n", $filename);

                        for ($i = $depth; $i > 0; $i--) {
                            if (isset($file[$error->getLine() - $i])) {
                                $class = '';
                                if ($i == 1) {
                                    $class = 'class="main-line"';
                                }
                                echo '<pre ' . $class . ' style="margin: 0.25rem;">' . ($error->getLine() + 1 - $i) . str_repeat(' ', 5 - (strlen(strval($error->getLine() - $i)))) . ($file[$error->getLine() - $i]) . '</pre>';
                            }
                        }

                        echo '<pre style="margin: 0.25rem;">' . ($error->getLine() + 1) . str_repeat(' ', 5 - (strlen(strval($error->getLine())))) . ($file[$error->getLine()]) . '</pre>';

                        for ($i = 1; $i <= $depth; $i++) {
                            if (isset($file[$error->getLine() + $i])) {
                                echo '<pre style="margin: 0.25rem;">' . ($error->getLine() + 1 + $i) . str_repeat(' ', 5 - (strlen(strval($error->getLine() + $i)))) . ($file[$error->getLine() + $i]) . '</pre>';
                            }
                        }

                        echo '</div>';
                    }
                    ?>

                    <pre style="margin: 0;white-space: nowrap;overflow: hidden; text-overflow: ellipsis; color:black !important;" class="black">[0] <?= ltrim($file[$error->getLine() - 1], '&nbsp;') ?></pre>
                </div>
                <?php foreach ($error->getTrace() as $id => $value) : ?>
                    <div class="item" <?= empty($value['file']) ? 'style="cursor: auto;"' : 'onclick="document.getElementById(\'file\').innerHTML = document.getElementById(' . ($id + 1) . ').innerHTML"' ?>>
                        <pre style="margin: 0; white-space: nowrap;overflow: hidden; text-overflow: ellipsis;">[<?= $id + 1 ?>] <?= htmlspecialchars(isset($value['class']) ? $value['class'] . $value['type'] . $value['function'] : $value['function']) ?></pre>

                        <?php
                        if (empty($value['file'])) {
                            echo '</div>';
                            continue;
                        }

                        echo '<div id="' . ($id + 1) . '" style="display: none">';
                        echo '<pre style="margin: 0.25rem;">' . htmlspecialchars($value['file'] ?? '-') . '</pre><hr>';
                        $depth = $depthReadFile;
                        $filename = @highlight_file($value['file'], true);
                        $file = explode(str_contains($filename, '<br />') ? '<br />' : "\n", $filename);

                        for ($i = $depth; $i > 0; $i--) {
                            if (isset($file[$value['line'] - $i])) {
                                $class = '';
                                if ($i == 1) {
                                    $class = 'class="main-line"';
                                }
                                echo '<pre ' . $class . ' style="margin: 0.25rem;">' . ($value['line'] + 1 - $i) . str_repeat(' ', 5 - (strlen(strval($value['line'] - $i)))) . ($file[$value['line'] - $i]) . '</pre>';
                            }
                        }

                        echo '<pre style="margin: 0.25rem;">' . ($value['line'] + 1) . str_repeat(' ', 5 - (strlen(strval($value['line'])))) . ($file[$value['line']]) . '</pre>';

                        for ($i = 1; $i <= $depth; $i++) {
                            if (isset($file[$value['line'] + $i])) {
                                echo '<pre style="margin: 0.25rem;">' . ($value['line'] + 1 + $i) . str_repeat(' ', 5 - (strlen(strval($value['line'] + $i)))) . ($file[$value['line'] + $i]) . '</pre>';
                            }
                        }

                        echo '</div>';
                        ?>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="file" id="file">
                <?php if (!empty($error->getFile())) {
                    echo '<pre style="margin: 0.25rem; white-space: nowrap;overflow: hidden; text-overflow: ellipsis;">' . htmlspecialchars($error->getFile()) . '</pre><hr>';

                    $depth = $depthReadFile;
                    $filename = @highlight_file($error->getFile(), true);
                    $file = explode(str_contains($filename, '<br />') ? '<br />' : "\n", $filename);

                    for ($i = $depth; $i > 0; $i--) {
                        if (isset($file[$error->getLine() - $i])) {
                            $class = '';
                            if ($i == 1) {
                                $class = 'class="main-line"';
                            }
                            echo '<pre ' . $class . ' style="margin: 0.25rem;">' . ($error->getLine() + 1 - $i) . str_repeat(' ', 5 - (strlen(strval($error->getLine() - $i)))) . ($file[$error->getLine() - $i]) . '</pre>';
                        }
                    }

                    echo '<pre style="margin: 0.25rem;">' . ($error->getLine() + 1) . str_repeat(' ', 5 - (strlen(strval($error->getLine())))) . ($file[$error->getLine()]) . '</pre>';

                    for ($i = 1; $i <= $depth; $i++) {
                        if (isset($file[$error->getLine() + $i])) {
                            echo '<pre style="margin: 0.25rem;">' . ($error->getLine() + 1 + $i) . str_repeat(' ', 5 - (strlen(strval($error->getLine() + $i)))) . ($file[$error->getLine() + $i]) . '</pre>';
                        }
                    }
                }
                ?>
            </div>
        </section>

        <?php if ($error instanceof \Core\Database\Exception\DatabaseException) : ?>
            <section class="database">
                <h3>Database</h3>
                <table style="table-layout: fixed; width: 100%; border-collapse: collapse;">
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                    </tr>
                    <?php foreach ($error->getInfoDriver() as $key => $value) : ?>
                        <tr>
                            <td><?= htmlspecialchars($key) ?></td>
                            <td><?= htmlspecialchars(is_array($value) ? implode(', ', $value) : (is_null($value) ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : strval($value)))) ?></td>
                        </tr>
                    <?php endforeach ?>
                </table>
            </section>
        <?php endif ?>

        <?php if (!empty(request()->all())) : ?>
            <section class="request">
                <h3>Request</h3>
                <table style="table-layout: fixed; width: 100%; border-collapse: collapse;">
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                    </tr>
                    <?php foreach (request()->all() as $key => $value) : ?>
                        <tr>
                            <td><?= htmlspecialchars($key) ?></td>
                            <td><?= htmlspecialchars(is_array($value) ? implode(', ', $value) : (is_null($value) ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : strval($value)))) ?></td>
                        </tr>
                    <?php endforeach ?>
                </table>
            </section>
        <?php endif ?>

        <section class="server">
            <h3>Server</h3>
            <table style="table-layout: fixed; width: 100%; border-collapse: collapse;">
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                </tr>
                <?php foreach (request()->server->all() as $key => $value) : ?>
                    <tr>
                        <td><?= htmlspecialchars($key) ?></td>
                        <td><?= htmlspecialchars(is_array($value) ? implode(', ', $value) : (is_null($value) ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : strval($value)))) ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </section>

    </main>
</body>

</html>