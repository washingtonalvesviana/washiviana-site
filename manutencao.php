<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Em Manutenção | Washiviana</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 20px;
        }
        
        .container {
            text-align: center;
            max-width: 600px;
            animation: fadeIn 1s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 20px;
            background: linear-gradient(90deg, #e94560, #0f3460);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        p {
            font-size: 1.2rem;
            color: #a0aec0;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
            overflow: hidden;
            margin: 30px 0;
        }
        
        .progress-bar::after {
            content: '';
            display: block;
            width: 30%;
            height: 100%;
            background: linear-gradient(90deg, #e94560, #0f3460);
            animation: loading 2s ease-in-out infinite;
        }
        
        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(400%); }
        }
        
        .contact {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .contact a {
            color: #e94560;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .contact a:hover {
            color: #fff;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 50px;
            color: #e94560;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">WASHIVIANA</div>
        <div class="icon">🚀</div>
        <h1>Aplicação em Manutenção</h1>
        <p>Estamos trabalhando em melhorias para oferecer uma experiência ainda melhor.<br>
        Voltaremos em breve com novo conteúdo!</p>
        <div class="progress-bar"></div>
        <div class="contact">
            <p>Enquanto isso, me encontre no <a href="https://www.linkedin.com/in/washington-alves-viana-38583269/" target="_blank">LinkedIn</a></p>
        </div>
    </div>
</body>
</html>

