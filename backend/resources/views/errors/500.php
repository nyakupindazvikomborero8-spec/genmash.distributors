<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #071030; color: #eef1ff; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .container { text-align: center; max-width: 400px; padding: 40px 20px; }
        h1 { font-size: 8rem; margin: 0; color: #e74c3c; line-height: 1; }
        h2 { font-size: 1.5rem; margin: 1rem 0; color: #aab4e0; font-weight: 500; }
        p { color: #aab4e0; margin-bottom: 2rem; }
        a { display: inline-block; background: #4b7bf5; color: #fff; padding: 14px 28px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: transform 0.2s; }
        a:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <h1>500</h1>
        <h2>Server Error</h2>
        <p>Something went wrong on our end. We've been notified and are working on it.</p>
        <a href="<?= config('app.url') ?>">Go Home</a>
    </div>
</body>
</html>