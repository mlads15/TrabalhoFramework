<?php
ini_set('display_errors', 1);
ini_set('display_startup_erros', 1);
error_reporting(E_ALL);

class Creator
{
    private $con;
    private $servidor;
    private $banco;
    private $usuario;
    private $senha;
    private $tabelas;
    private $nomeSistema;

    public function __construct()
    {
        if (isset($_GET['id'])) {
            $this->listarBancos();
            exit; // Importante: sair após listar bancos
        } else {
            $this->iniciarCriacao();
        }
    }

    private function iniciarCriacao()
    {
        $this->criarEstrutura();
        $this->conectar(1);
        $this->buscarTabelas();
        $this->nomeSistema = $_POST["nome"];
        
        $this->criarIndex();
        $this->criarModels();
        $this->criarConexao();
        $this->criarControllers();
        $this->criarViews();
        $this->criarDaos();

        $this->compactarSistema();
        header("Location: index.php?msg=2");
        exit;
    }

    private function criarEstrutura()
    {
        $diretorios = ["sistema", "sistema/model", "sistema/control", "sistema/view", "sistema/dao", "sistema/css"];
        
        foreach ($diretorios as $dir) {
            if (!file_exists($dir) && !mkdir($dir, 0777, true)) {
                header("Location: index.php?msg=0");
                exit;
            }
        }
        copy('estilos.css', 'sistema/css/estilos.css');
    }

    private function conectar($tipo)
    {
        $this->servidor = $_REQUEST["servidor"] ?? $_POST["servidor"];
        $this->usuario = $_REQUEST["usuario"] ?? $_POST["usuario"];
        $this->senha = $_REQUEST["senha"] ?? $_POST["senha"];
        $this->banco = $tipo == 1 ? $_POST["banco"] : "mysql";

        try {
            $this->con = new PDO(
                "mysql:host={$this->servidor};dbname={$this->banco}",
                $this->usuario,
                $this->senha,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Exception $e) {
            if (isset($_GET['id'])) {
                echo "<option value=''>Erro de conexão</option>";
                exit;
            } else {
                header("Location: index.php?msg=1");
                exit;
            }
        }
    }

    private function listarBancos()
    {
        try {
            $this->conectar(0);
            $bancos = $this->con->query("SHOW databases")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($bancos as $banco) {
                $dbName = $banco['Database'];
                // Filtra bancos do sistema
                if (!in_array($dbName, ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
                    echo "<option value='{$dbName}'>{$dbName}</option>";
                }
            }
        } catch (Exception $e) {
            echo "<option value=''>Erro ao carregar bancos</option>";
        }
    }
    
    private function buscarTabelas()
    {
        $this->tabelas = $this->con->query("SHOW TABLES")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buscarAtributos($tabela)
    {
        return $this->con->query("SHOW COLUMNS FROM {$tabela}")->fetchAll(PDO::FETCH_OBJ);
    }

    private function criarModels()
    {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = current($tabela);
            $atributos = $this->buscarAtributos($nomeTabela);
            $classe = ucfirst($nomeTabela);
            
            $props = $getsSets = "";
            foreach ($atributos as $attr) {
                $nome = $attr->Field;
                $metodo = ucfirst($nome);
                
                $props .= "\tprivate \${$nome};\n";
                $getsSets .= "\tpublic function get{$metodo}() { return \$this->{$nome}; }\n";
                $getsSets .= "\tpublic function set{$metodo}(\${$nome}) { \$this->{$nome} = \${$nome}; }\n";
            }

            $conteudo = "<?php\nclass {$classe} {\n{$props}\n{$getsSets}}\n?>";
            file_put_contents("sistema/model/{$nomeTabela}.php", $conteudo);
        }
    }

    private function criarConexao()
    {
        $conteudo = "<?php
class Conexao {
    private \$server = '{$this->servidor}';
    private \$banco = '{$this->banco}';
    private \$usuario = '{$this->usuario}';
    private \$senha = '{$this->senha}';
    
    public function conectar() {
        try {
            return new PDO(\"mysql:host={\$this->server};dbname={\$this->banco}\", \$this->usuario, \$this->senha);
        } catch (Exception \$e) {
            die(\"Erro ao conectar: \" . \$e->getMessage());
        }
    }
}\n?>";
        
        file_put_contents("sistema/model/conexao.php", $conteudo);
    }

    private function criarControllers()
    {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = current($tabela);
            $classe = ucfirst($nomeTabela);
            $atributos = $this->buscarAtributos($nomeTabela);
            
            $posts = "";
            foreach ($atributos as $attr) {
                if ($attr->Key != "PRI") {
                    $nome = $attr->Field;
                    $posts .= "\$this->{$nomeTabela}->set" . ucfirst($nome) . "(\$_POST['{$nome}']);\n\t\t";
                }
            }

            $conteudo = "<?php
require_once '../model/{$nomeTabela}.php';
require_once '../dao/{$nomeTabela}Dao.php';

class {$classe}Control {
    private \${$nomeTabela};
    private \$dao;
    
    public function __construct() {
        \$this->{$nomeTabela} = new {$classe}();
        \$this->dao = new {$classe}Dao();
        \$this->executarAcao();
    }
    
    private function executarAcao() {
        switch (\$_GET['a']) {
            case 1: \$this->inserir(); break;
            case 2: \$this->excluir(); break;
            case 3: \$this->alterar(); break;
        }
    }
    
    private function inserir() {
        {$posts}
        \$this->dao->inserir(\$this->{$nomeTabela});
    }
    
    private function excluir() {
        \$this->dao->excluir(\$_GET['id']);
    }
    
    private function alterar() {
        {$posts}
        \$this->dao->alterar(\$this->{$nomeTabela}, \$_GET['id']);
    }
}

new {$classe}Control();\n?>";
            
            file_put_contents("sistema/control/{$nomeTabela}Control.php", $conteudo);
        }
    }

    private function criarDaos()
    {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = current($tabela);
            $classe = ucfirst($nomeTabela);
            $atributos = $this->buscarAtributos($nomeTabela);
            
            // Encontrar chave primária
            $id = "";
            foreach ($atributos as $attr) {
                if ($attr->Key == "PRI") $id = $attr->Field;
            }
            
            // Filtrar atributos não-PRI
            $campos = array_filter($atributos, fn($a) => $a->Key != "PRI");
            $nomesCampos = array_map(fn($a) => $a->Field, $campos);
            
            $cols = implode(', ', $nomesCampos);
            $placeholders = implode(', ', array_fill(0, count($nomesCampos), '?'));
            $updateSet = implode('=?, ', $nomesCampos) . '=?';
            
            $bindings = $valores = "";
            foreach ($nomesCampos as $campo) {
                $metodo = ucfirst($campo);
                $bindings .= "\${$campo} = \$obj->get{$metodo}();\n\t\t";
                $valores .= "\${$campo}, ";
            }
            $valores = rtrim($valores, ', ');

            $conteudo = "<?php
require_once '../model/conexao.php';

class {$classe}Dao {
    private \$con;
    
    public function __construct() {
        \$this->con = (new Conexao())->conectar();
    }
    
    public function inserir(\$obj) {
        \$sql = \"INSERT INTO {$nomeTabela} ({$cols}) VALUES ({$placeholders})\";
        \$stmt = \$this->con->prepare(\$sql);
        {$bindings}
        \$stmt->execute([{$valores}]);
        header('Location: ../view/lista{$classe}.php');
    }
    
    public function listar() {
        return \$this->con->query(\"SELECT * FROM {$nomeTabela}\")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function excluir(\$id) {
        \$this->con->query(\"DELETE FROM {$nomeTabela} WHERE {$id} = \$id\");
        header('Location: ../view/lista{$classe}.php');
    }
    
    public function buscarPorId(\$id) {
        return \$this->con->query(\"SELECT * FROM {$nomeTabela} WHERE {$id} = \$id\")->fetch(PDO::FETCH_ASSOC);
    }
    
    public function alterar(\$obj, \$id) {
        \$sql = \"UPDATE {$nomeTabela} SET {$updateSet} WHERE {$id} = ?\";
        \$stmt = \$this->con->prepare(\$sql);
        {$bindings}
        \$stmt->execute([{$valores}, \$id]);
        header('Location: ../view/lista{$classe}.php');
    }
}\n?>";
            
            file_put_contents("sistema/dao/{$nomeTabela}Dao.php", $conteudo);
        }
    }

    private function criarViews()
    {
        foreach ($this->tabelas as $tabela) {
            $this->criarFormulario($tabela);
            $this->criarLista($tabela);
        }
    }

    private function criarFormulario($tabela)
    {
        $nomeTabela = current($tabela);
        $classe = ucfirst($nomeTabela);
        $atributos = $this->buscarAtributos($nomeTabela);
        
        $campos = "";
        foreach ($atributos as $attr) {
            $nome = $attr->Field;
            $tipo = $this->getTipoInput($attr);
            $label = ucfirst(str_replace('_', ' ', $nome));
            
            if ($tipo == 'hidden') {
                $campos .= "<input type='hidden' name='{$nome}' value='<?= \$obj[\"{$nome}\"] ?? \"\" ?>'>\n";
            } else {
                $campos .= "<div class='campo'>\n";
                $campos .= "<label>{$label}</label>\n";
                $campos .= "<input type='{$tipo}' name='{$nome}' value='<?= \$obj[\"{$nome}\"] ?? \"\" ?>' required>\n";
                $campos .= "</div>\n";
            }
        }

        $conteudo = "<?php
require_once '../dao/{$nomeTabela}Dao.php';
\$obj = isset(\$_GET['id']) ? (new {$classe}Dao())->buscarPorId(\$_GET['id']) : null;
\$acao = \$obj ? 3 : 1;
\$titulo = \$obj ? 'Editar' : 'Cadastrar';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title><?= \$titulo ?> {$classe}</title>
    <link rel='stylesheet' href='../../estilos.css'>
</head>
<body class='form-page'>
    <div class='container'>
        <form action='../control/{$nomeTabela}Control.php?a=<?= \$acao ?><?= \$obj ? \"&id={\$obj['id']}\" : \"\" ?>' method='post'>
            <h2><?= \$titulo ?> {$classe}</h2>
            {$campos}
            <button type='submit'><?= \$obj ? 'Atualizar' : 'Cadastrar' ?></button>
        </form>
    </div>
</body>
</html>";
        
        file_put_contents("sistema/view/{$nomeTabela}.php", $conteudo);
    }

    private function criarLista($tabela)
    {
        $nomeTabela = current($tabela);
        $classe = ucfirst($nomeTabela);
        $atributos = $this->buscarAtributos($nomeTabela);
        
        $cols = $linhas = "";
        foreach ($atributos as $attr) {
            $nome = $attr->Field;
            $label = ucfirst(str_replace('_', ' ', $nome));
            $cols .= "<th>{$label}</th>\n";
            $linhas .= "echo \"<td>{\$dado['{$nome}']}</td>\";\n";
        }

        $conteudo = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Lista de {$classe}</title>
    <link rel='stylesheet' href='../../estilos.css'>
</head>
<body class='form-page'>
    <div class='container'>
        <h2>Lista de {$classe}</h2>
        <a href='{$nomeTabela}.php' class='btn'>+ Novo</a>
        
        <?php
        require_once '../dao/{$nomeTabela}Dao.php';
        \$dados = (new {$classe}Dao())->listar();
        
        if (!empty(\$dados)) {
            echo \"<table><tr>{$cols}<th>Ações</th></tr>\";
            foreach(\$dados as \$dado) {
                echo \"<tr>\";
                {$linhas}
                echo \"<td>
                    <a href='{$nomeTabela}.php?id={\$dado['id']}'>✏️</a>
                    <a href='../control/{$nomeTabela}Control.php?a=2&id={\$dado['id']}' onclick='return confirm(\\\"Confirma?\\\")'>Excluir</a>
                </td></tr>\";
            }
            echo \"</table>\";
        } else {
            echo \"<p>Nenhum registro</p>\";
        }
        ?>
    </div>
</body>
</html>";
        
        file_put_contents("sistema/view/lista{$classe}.php", $conteudo);
    }

    private function criarIndex()
    {
        $forms = $listas = "";
        foreach ($this->tabelas as $tabela) {
            $nome = ucfirst(current($tabela));
            $forms .= "<a href='./view/{$nome}.php' target='iframe'>{$nome}</a>\n";
            $listas .= "<a href='./view/lista{$nome}.php' target='iframe'>{$nome}</a>\n";
        }

        $conteudo = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>{$this->nomeSistema}</title>
    <link rel='stylesheet' href='./css/estilos.css'>
</head>
<body class='sistema'>
    <header>
        <h1>{$this->nomeSistema}</h1>
        <nav>
            <a href='#'>Inserir</a>
            <div>{$forms}</div>
            <a href='#'>Listar</a>
            <div>{$listas}</div>
        </nav>
    </header>
    <main>
        <iframe name='iframe' src='inicio.html'></iframe>
    </main>
</body>
</html>";
        
        file_put_contents("sistema/index.php", $conteudo);
        
        file_put_contents("sistema/inicio.html", "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><title>Início</title></head>
<body><h1>Bem-vindo ao {$this->nomeSistema}</h1><p>Use o menu para navegar.</p></body>
</html>");
    }

    private function getTipoInput($atributo)
    {
        if ($atributo->Key == "PRI") return "hidden";
        if (str_contains($atributo->Type, 'int')) return "number";
        if (str_contains($atributo->Type, 'date')) return "date";
        return "text";
    }

    private function compactarSistema()
    {
        $zip = new ZipArchive();
        if ($zip->open('sistema.zip', ZipArchive::CREATE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator('sistema'),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen('sistema') + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        }
    }
}

new Creator();
