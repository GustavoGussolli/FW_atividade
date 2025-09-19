<?php
ini_set('display_errors',1);
ini_set('display_startup_erros',1);
error_reporting(E_ALL);
class Creator {
    private $con;
    private $servidor ;
    private $banco;
    private $usuario;
    private $senha;
    private $tabelas;

    function __construct() {
        if(isset($_GET['id']))
            $this->buscaBancodeDados();
        else {
            $this->criaDiretorios();
            $this->conectar(1);
            $this->buscaTabelas();
            $this->ClassesModel();
            $this->ClasseConexao();
            $this->ClassesControl();
            $this->classesView();
            $this->ClassesDao();
            $this->compactar();
            header("Location:index.php?msg=2");
        }
    }//fimConsytruct
    function criaDiretorios() {
        $dirs = [
            "sistema",
            "sistema/model",
            "sistema/control",
            "sistema/view",
            "sistema/dao",
            "sistema/css"
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    header("Location:index.php?msg=0");
                }
            }
        }
        copy('estilos.css','sistema/css/estilos.css');
    }//fimDiretorios
    function conectar($id){
        $this->servidor = $_REQUEST["servidor"];
        $this->usuario = $_REQUEST["usuario"];
        $this->senha = $_REQUEST["senha"];
        if ($id == 1) {
           $this->banco = $_POST["banco"];
        }
        else{
            $this->banco = "mysql";


        }
        try {
            $this->con = new PDO(
                "mysql:host=" . $this->servidor . ";dbname=" . $this->banco,
                $this->usuario,
                $this->senha
            );
        } catch (Exception $e) {

           header("Location:index.php?msg=1");
        }
    }//fimConectar
    function buscaBancodeDados(){
        try {
                $this->conectar(0);
                $sql = "SHOW databases";
                $query = $this->con->query($sql);
                $databases = $query->fetchAll(PDO::FETCH_ASSOC);
                foreach ($databases as $database){
                    echo "<option>".$database["Database"]."</option>";
                }
                $this->con=null;
            }
        catch (Exception $e) {
            header("Location:index.php?msg=3");

        }
    }//uscaBD
    function buscaTabelas(){
       try {
           $sql = "SHOW TABLES";
           $query = $this->con->query($sql);
           $this->tabelas = $query->fetchAll(PDO::FETCH_ASSOC);
       }
       catch (Exception $e) {
           header("Location:index.php?msg=3");
       }
    }//fimBuscaTabelas
    function buscaAtributos($nomeTabela){
        $sql="show columns from ".$nomeTabela;
        $atributos = $this->con->query($sql)->fetchAll(PDO::FETCH_OBJ);
        return $atributos;
    }//fimBuscaAtributos
    function ClassesModel() {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array) $tabela)[0];
            $atributos=$this->buscaAtributos($nomeTabela);
            $nomeAtributos="";
            $geters_seters="";
            foreach ($atributos as $atributo) {
                $atributo=$atributo->Field;
                $nomeAtributos.="\tprivate \${$atributo};\n";
                $metodo=ucfirst($atributo);
                $geters_seters.="\tfunction get".$metodo."(){\n";
                $geters_seters.="\t\treturn \$this->{$atributo};\n\t}\n";
                $geters_seters.="\tfunction set".$metodo."(\${$atributo}){\n";
                $geters_seters.="\t\t\$this->{$atributo}=\${$atributo};\n\t}\n";
            }
            $nomeTabela=ucfirst($nomeTabela);
            $conteudo = <<<EOT
<?php
class {$nomeTabela} {
{$nomeAtributos}
{$geters_seters}
}
?>
EOT;
            file_put_contents("sistema/model/{$nomeTabela}.php", $conteudo);

        }
    }//fimModel
    function classesView() {
        //formulários de cadastro (existente)
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array) $tabela)[0];
            $atributos=$this->buscaAtributos($nomeTabela);
            $formCampos="";
           foreach ($atributos as $atributo) {
                $atributo=$atributo->Field;
                $formCampos .= "<label for='{$atributo}'>{$atributo}</label>\n";
                $formCampos .= "<input type='text' name='{$atributo}'><br>\n";
            }
            $conteudo = <<<HTML
<html>
    <head>
        <title>Cadastro de {$nomeTabela}</title>
        <link rel="stylesheet" href="../css/estilos.css">
    </head>
    <body>
        <form action="../control/{$nomeTabela}Control.php?a=1" method="post">
        <h1>Cadastro de {$nomeTabela}</h1>
            {$formCampos}
             <button type="submit">Enviar</button>
        </form>
    </body>
</html>
HTML;
  file_put_contents("sistema/view/{$nomeTabela}.php", $conteudo); // Exemplo salvando como arquivo
        }

        //Listas (modificado para incluir link de edição)
        foreach ($this->tabelas as $tabela) {
             $nomeTabela = array_values((array)$tabela)[0];
             $nomeTabelaUC=ucfirst($nomeTabela);
             $atributos=$this->buscaAtributos($nomeTabela);
             $attr = "";
             $idField=""; // Nome do campo da chave primária
             foreach($atributos as $atributo){
                if($atributo->Key=="PRI")
                    $idField=$atributo->Field;

                $attr.="echo \"<td>{\$dado['{$atributo->Field}']}</td>\";\n";
              }
            $conteudo="";
            $conteudo = <<<HTML

<html>
    <head>
        <title>Lista de {$nomeTabelaUC}</title>
        <link rel="stylesheet" href="../css/estilos.css">
    </head>
    <body>
      <?php
      require_once("../dao/{$nomeTabelaUC}Dao.php");
   \$dao=new {$nomeTabelaUC}Dao();
   \$dados=\$dao->listaGeral();
   echo"<table border=1>";
    foreach(\$dados as \$dado){
        echo "<tr>";
       {$attr};
       echo "<td><a href='../control/{$nomeTabela}Control.php?a=2&id={\$dado['{$idField}']}'>Excluir</a></td>";
       echo "<td><a href='../view/FormularioEdicao{$nomeTabelaUC}.php?id={\$dado['{$idField}']}'>Alterar</a></td>"; // Link para o formulário de edição
       echo "<tr>";
    }
    echo "</table>";
     ?>  
    </body>
</html>
HTML;           
  file_put_contents("sistema/view/Lista{$nomeTabela}.php", $conteudo);        
        }

        // Novo formulário de edição
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array) $tabela)[0];
            $nomeTabelaUC = ucfirst($nomeTabela);
            $atributos = $this->buscaAtributos($nomeTabela);
            $formCampos = "";
            $idField = "";

            foreach ($atributos as $atributo) {
                $atributoName = $atributo->Field;
                if ($atributo->Key == "PRI") {
                    $idField = $atributoName;
                    // O campo ID será oculto e pré-preenchido
                    $formCampos .= "<input type='hidden' name='{$atributoName}' value='<?php echo \$registro['{$atributoName}']; ?>'>\n";
                } else {
                    $formCampos .= "<label for='{$atributoName}'>{$atributoName}</label>\n";
                    $formCampos .= "<input type='text' name='{$atributoName}' value='<?php echo \$registro['{$atributoName}']; ?>'><br>\n";
                }
            }

            $conteudo = <<<HTML
<html>
    <head>
        <title>Editar {$nomeTabelaUC}</title>
        <link rel="stylesheet" href="../css/estilos.css">
    </head>
    <body>
        <?php
        require_once("../dao/{$nomeTabelaUC}Dao.php");
        \$dao = new {$nomeTabelaUC}Dao();
        \$id = \$_GET['id'];
        \$registro = \$dao->buscarPorId(\$id); // Precisamos de um método buscarPorId na DAO
        ?>
        <form action="../control/{$nomeTabela}Control.php?a=3" method="post">
        <h1>Editar {$nomeTabelaUC}</h1>
            {$formCampos}
             <button type="submit">Salvar Alterações</button>
        </form>
    </body>
</html>
HTML;
            file_put_contents("sistema/view/FormularioEdicao{$nomeTabelaUC}.php", $conteudo);
        }
    }//fimView
   
function ClasseConexao(){
        $conteudo = <<<EOT

<?php
class Conexao {
    private \$server;
    private \$banco;
    private \$usuario;
    private \$senha;
    function __construct() {
        \$this->server = '{$this->servidor}';
        \$this->banco = '{$this->banco}';
        \$this->usuario = '{$this->usuario}';
        \$this->senha = '{$this->senha}';
    }
    
    function conectar() {
        try {
            \$conn = new PDO(
                "mysql:host=" . \$this->server . ";dbname=" . \$this->banco,\$this->usuario,
                \$this->senha
            );
            return \$conn;
        } catch (Exception \$e) {
            echo "Erro ao conectar com o Banco de dados: " . \$e->getMessage();
        }
    }
}
?>
EOT;
        file_put_contents("sistema/model/conexao.php", $conteudo);
    }//fimConexao
    function ClassesControl(){
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array)$tabela)[0];
            $atributos=$this->buscaAtributos($nomeTabela);
            $nomeClasse=ucfirst($nomeTabela);
            $posts="";
            $primaryKeyField = ""; // Para armazenar o nome do campo da chave primária

            foreach ($atributos as $atributo) {
                $atributoName = $atributo->Field;
                $posts.= "\$this->{$nomeTabela}->set".ucFirst($atributoName).
                    "(\$_POST['{$atributoName}']);\n\t\t";
                if ($atributo->Key == "PRI") {
                    $primaryKeyField = $atributoName;
                }
            }

            $conteudo = <<<EOT
<?php
require_once("../model/{$nomeClasse}.php");
require_once("../dao/{$nomeClasse}Dao.php");
class {$nomeClasse}Control {
    private \${$nomeTabela};
    private \$acao;
    private \$dao;
    public function __construct(){
       \$this->{$nomeTabela}=new {$nomeClasse}();
      \$this->dao=new {$nomeClasse}Dao();
      \$this->acao=\$_GET["a"];
      \$this->verificaAcao(); 
    }
    function verificaAcao(){
       switch(\$this->acao){
          case 1:
            \$this->inserir();
          break;
          case 2:
            \$this->excluir();
            break;
          case 3: // Novo case para edição
            \$this->alterar();
            break;
       }
    }
    function inserir(){
        {$posts}
        \$this->dao->inserir(\$this->{$nomeTabela});
    }
    function excluir(){
        \$this->dao->excluir(\$_REQUEST['id']);
    }
    function alterar(){ // Novo método para alterar
        // Preenche o objeto com os dados do POST
        {$posts}
        // Define a chave primária para o objeto
        \$this->{$nomeTabela}->set{$primaryKeyField}( \$_POST['{$primaryKeyField}'] );
        \$this->dao->alterar(\$this->{$nomeTabela});
    }
    function buscarId({$nomeClasse} \${$nomeTabela}){}
    function buscaTodos(){}

}
new {$nomeClasse}Control();
?>
EOT;
            file_put_contents("sistema/control/{$nomeTabela}Control.php", $conteudo);
        }

    }//fimControl
    function compactar() {
        $folderToZip = 'sistema';
        $outputZip = 'sistema.zip';
        $zip = new ZipArchive();
        if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }
        $folderPath = realpath($folderToZip);  // Corrigido aqui
        if (!is_dir($folderPath)) {
            return false;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folderPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($folderPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        return $zip->close();
    }//fimCompactar
    
function ClassesDao(){
     foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array)$tabela)[0];
            $nomeClasse = ucfirst($nomeTabela);
            $atributos=$this->buscaAtributos($nomeTabela);
            $id ="";
            $updateFields = []; // Para construir a parte SET da query de update

            foreach($atributos as $atributo){
                if($atributo->Key == "PRI") {
                    $id = $atributo->Field;
                }
                // Adiciona todos os campos para a parte SET, exceto a chave primária
                $updateFields[] = "{$atributo->Field} = ?";
            }
            // Remove a chave primária da lista de campos para o SET, pois ela será usada no WHERE
            $updateFields = array_filter($updateFields, function($field) use ($id) {
                return strpos($field, "{$id} = ?") === false;
            });

            $sqlCols = implode(', ', array_map(function($obj) { return $obj->Field; }, $atributos));
            $placeholders = implode(', ', array_fill(0, count($atributos), '?'));
         
            $vetAtributos=[];
            $AtributosMetodos="";
            foreach ($atributos as $atributo) {
                $atr=ucfirst($atributo->Field);
                array_push($vetAtributos,"\${$atributo->Field}");
                $AtributosMetodos.="\${$atributo->Field}=\$obj->get{$atr}();\n";
            }
            $atributosOk=implode(",",$vetAtributos);

            // Atributos para a query de update (todos exceto a chave primária, e a chave primária no final)
            $updateAtributos = [];
            $updateAtributosMetodos = "";
            $primaryKeyGetter = "";

            foreach ($atributos as $atributo) {
                $atrName = $atributo->Field;
                $atrUcfirst = ucfirst($atrName);
                if ($atributo->Key != "PRI") {
                    $updateAtributos[] = "\${$atrName}";
                    $updateAtributosMetodos .= "\${$atrName}=\$obj->get{$atrUcfirst}();\n";
                } else {
                    $primaryKeyGetter = "\$obj->get{$atrUcfirst}()";
                }
            }
            $updateAtributos[] = $primaryKeyGetter; // Adiciona a chave primária no final para o WHERE
            $updateAtributosOk = implode(",",$updateAtributos);
            $setClause = implode(', ', $updateFields);


         $conteudo = <<<EOT
<?php
require_once("../model/conexao.php");
class {$nomeClasse}Dao {
    private \$con;
    public function __construct(){
       \$this->con=(new Conexao())->conectar();
    }
function inserir(\$obj) {
    \$sql = "INSERT INTO {$nomeTabela} ({$sqlCols}) VALUES ({$placeholders})";
    \$stmt = \$this->con->prepare(\$sql);
    {$AtributosMetodos}
    \$stmt->execute([{$atributosOk}]);
    header("Location:../view/Lista{$nomeClasse}.php");
}
function listaGeral(){
    \$sql = "select * from {$nomeTabela}";
    \$query = \$this->con->query(\$sql);
    \$dados = \$query->fetchAll(PDO::FETCH_ASSOC);
    return \$dados;
}
function excluir(\$id){
    \$sql = "DELETE FROM {$nomeTabela} WHERE {$id}=:id";
    \$stmt = \$this->con->prepare(\$sql);
    \$stmt->bindParam(':id', \$id);
    \$stmt->execute();
    header("Location:../view/Lista{$nomeClasse}.php");
}
function alterar(\$obj){ // Novo método para alterar
    \$sql = "UPDATE {$nomeTabela} SET {$setClause} WHERE {$id} = ?";
    \$stmt = \$this->con->prepare(\$sql);
    {$updateAtributosMetodos}
    \$stmt->execute([{$updateAtributosOk}]);
    header("Location:../view/Lista{$nomeClasse}.php");
}
function buscarPorId(\$id){ // Novo método para buscar por ID
    \$sql = "SELECT * FROM {$nomeTabela} WHERE {$id} = :id";
    \$stmt = \$this->con->prepare(\$sql);
    \$stmt->bindParam(':id', \$id);
    \$stmt->execute();
    return \$stmt->fetch(PDO::FETCH_ASSOC);
}
}
?>
EOT;
            file_put_contents("sistema/dao/{$nomeClasse}Dao.php", $conteudo);
        }

    }//fimDao

}
new Creator();
