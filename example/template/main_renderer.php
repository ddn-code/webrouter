<html lang="en" class="h-100">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Carlos A.">
    <title>Coming soon</title>
    <!-- Bootstrap core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="theme-color" content="#7952b3">
    <style>
        pre.debug {
            background-color: #f5b0b0;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 12px;
            padding: 2px;
            margin: 2px;
            text-align: left;
        }
        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
        }

        @media (min-width: 768px) {
            .bd-placeholder-img-lg {
                font-size: 3.5rem;
            }
        }
    </style>
</head>

<body class="h-100">
    <div class="container py-3 d-flex w-100 h-100">
        <div class="my-auto w-100 text-center">
            <h2 class="mb-5">Coming soon...</h2>
            <div class="row text-center w-50 mx-auto">
                <div class="col">
                    <div class="rounded-3 shadow-sm border">
                        <h4 class="py-5 fw-normal">0 <span class="small text-muted">days</span></h4>
                    </div>
                </div>
                <div class="col">
                    <div class="rounded-3 shadow-sm border">
                        <h4 class="py-5 fw-normal">0 <span class="small text-muted">hours</span></h4>
                    </div>
                </div>
                <div class="col">
                    <div class="rounded-3 shadow-sm border">
                        <h4 class="py-5 fw-normal">0 <span class="small text-muted">min</h4>
                    </div>
                </div>
                <div class="col">
                    <div class="rounded-3 shadow-sm border">
                        <h4 class="py-5 fw-normal">0 <span class="small text-muted">sec</h4>
                    </div>
                </div>
            </div>
            <div class="container w-50 my-5 rounded-3 border">
                <?php echo $_VIEW; ?>
            </div>
        </div>
    </div>
</body>
</html>

