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
        }

        pre {
            font-size: 1.4rem;
            font-weight: bold;
            overflow: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .font {
            font-family: monospace;
            font-size: 0.94rem;
        }

        th {
            background-color: #aaaaaa;
        }

        td {
            text-align: left;
            height: 1.55rem;
            border-bottom: 0.05rem solid #bbb;
        }

        tr:hover {
            background-color: #cccccc;
        }
    </style>
</head>

<body>
    <pre><?= e($error->getMessage()) ?></pre>
    <div style="overflow-x: auto; padding-bottom: 2rem">
        <div class="font">
            <p><?= e($error->getFile()) . '::' . e($error->getLine()) ?></p>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <th>No</th>
                    <th>File</th>
                    <th>Line</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($error->getTrace() as $id => $value) : ?>
                    <tr>
                        <td><?= $id + 1 ?></td>
                        <td><?= e(str_replace(basepath(), '', $value['file'] ?? '-')) ?></td>
                        <td><?= e($value['line'] ?? '-') ?></td>
                        <td><?= e(@$value['class'] ? $value['class'] . $value['type'] . $value['function'] : $value['function']) ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
    </div>
</body>

</html>