<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Sistema</title>
    <link rel="stylesheet" href="estilos.css">
    <script src="js/funcoes.js"></script>

</head>

<body class="form-page">

    <div class="container">

        <form id="form-config" class="form-moderno" action="creator.php" method="POST">
            
            <?php include 'mensagens.php'; ?>
            <?php if (isset($_GET['msg'])): ?>
                <div class="<?= $_GET['msg'] == 2 ? 'mensagem' : 'mensagem_erro' ?>">
                    <?= $mensagens[$_GET['msg']] ?? "Erro desconhecido" ?>
                </div>
            <?php endif; ?>

            <h2>EasyMVC</h2>

            <div class="campo">

                <label>Servidor</label>
                <input type="text" name="servidor" placeholder="localhost" required>

            </div>

            <div class="campo">

                <label>Usuário</label>
                <input type="text" name="usuario" placeholder="root" required>

            </div>

            <div class="campo">
                
                <label>Senha</label>
                <input type="password" name="senha" placeholder="••••••••" onblur="carregarBanco()">

            </div>

            <div class="campo">

                <label>Banco de Dados</label>
                <select name="banco" required>
                    <option value="">Selecione um banco</option>
                </select>

            </div>

            <div id="carregando"></div>

            <button type="submit">Gerar Sistema</button>

        </form>

    </div>
    
</body>
</html>