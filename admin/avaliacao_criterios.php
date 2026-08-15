<?php
require __DIR__.'/_layout.php';
$u=require_admin();if(!is_general_admin($u)){http_response_code(403);exit('Acesso negado.');}
admin_header('Critérios de Avaliação');?>
<section class="panel"><div class="alert ok">Na versão atual, cada teste é iniciado pelo próprio instrutor e os critérios detalhados permanecem na área dele. O Admin recebe somente o relatório final com as notas dos alunos.</div><a class="primary small-button" href="avaliacoes.php">VOLTAR AOS RELATÓRIOS FINAIS</a></section>
<?php admin_footer();?>
