<?php
require __DIR__.'/_layout.php';
require __DIR__.'/_doc_helpers.php';
$u=require_admin();
if(!is_general_admin($u)){http_response_code(403);exit('Somente o Admin Geral pode editar os documentos.');}
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        $action=(string)($_POST['action']??'save');
        if($action==='reset_safe_fonts'){
            $safe=['doc_card_title_size'=>'4.0','doc_card_name_size'=>'2.8','doc_card_field_size'=>'2.1','doc_card_label_size'=>'1.55','doc_badge_title_size'=>'3.6','doc_badge_name_size'=>'4.4','doc_badge_field_size'=>'2.3','doc_badge_label_size'=>'1.85','doc_back_title_size'=>'3.7','doc_back_text_size'=>'1.7'];
            foreach($safe as $k=>$v)set_setting($k,$v);
            go('documentos.php','Tamanhos seguros restaurados.');
        }
        $colorDefaults=['doc_primary_color'=>'#0b1118','doc_gold_color'=>'#d1a33f','doc_wine_color'=>'#7d1020','doc_light_color'=>'#f5f1e9','doc_text_color'=>'#171717'];
        foreach($colorDefaults as $k=>$default){$v=trim((string)($_POST[$k]??$default));set_setting($k,preg_match('/^#[0-9a-fA-F]{6}$/',$v)?$v:$default);}
        $textKeys=['doc_student_card_title','doc_student_badge_title','doc_team_card_title','doc_team_badge_title','doc_companion_card_title','doc_companion_badge_title','doc_student_name_label','doc_preferred_name_label','doc_number_label','doc_role_label','doc_team_number_label','doc_team_role_label','doc_companion_number_label','doc_companion_role_label','doc_validity_label','doc_qr_label','doc_motto'];
        foreach($textKeys as $k)set_setting($k,bamab_doc_safe_text((string)($_POST[$k]??''),100));
        foreach(['doc_student_card_name_mode','doc_team_card_name_mode'] as $k){$v=(string)($_POST[$k]??'first_last_surname');if(!in_array($v,['first_first_surname','first_last_surname','nickname'],true))$v='first_last_surname';set_setting($k,$v);}
        $fontRules=[
          'doc_card_title_size'=>[4.2,2.4,5.2],'doc_card_name_size'=>[2.8,2.0,3.6],'doc_card_field_size'=>[2.2,1.65,2.8],'doc_card_label_size'=>[1.65,1.2,2.1],
          'doc_badge_title_size'=>[3.8,2.6,4.8],'doc_badge_name_size'=>[4.7,2.9,5.6],'doc_badge_field_size'=>[2.45,1.75,3.1],'doc_badge_label_size'=>[2.0,1.35,2.4],
          'doc_back_title_size'=>[4.0,2.5,4.8],'doc_back_text_size'=>[1.85,1.3,2.25]
        ];
        foreach($fontRules as $k=>$rule){$raw=$_POST[$k]??$rule[0];$v=is_numeric($raw)?(float)$raw:(float)$rule[0];$v=max($rule[1],min($rule[2],$v));set_setting($k,rtrim(rtrim(number_format($v,2,'.',''),'0'),'.'));}
        foreach(['doc_show_preferred_name','doc_show_qr_on_front','doc_show_qr_on_back','doc_show_motto'] as $k)set_setting($k,isset($_POST[$k])?'1':'0');
        foreach(['card_back_notice','badge_back_rules','team_card_back_notice','companion_card_back_notice'] as $k)set_setting($k,bamab_doc_safe_text((string)($_POST[$k]??''),900));
        go('documentos.php','Configuração salva com validação de segurança para fontes e textos.');
    }catch(Throwable $e){$err=$e->getMessage();}
}
admin_header('Editor de Carteirinhas e Crachás');$msg=flash();
?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<section class="panel doc-editor-intro">
 <div class="panel-heading-flex"><div><span>EDITOR 100% ADMIN</span><h2>Carteirinhas e crachás</h2><p>As configurações abaixo valem para aluno e equipe. Você pode alterar textos, cores e tamanhos das fontes sem editar código. Diplomas e certificados usam estas mesmas cores.</p><p><a class="small-button" href="reconhecimento_config.php">EDITAR DIPLOMAS, CERTIFICADOS E ASSINATURAS</a></p></div></div>
</section>
<section class="panel doc-print-standard"><div class="panel-heading-flex"><div><span>PADRÃO DE IMPRESSÃO TRAVADO</span><h2>Tamanhos de impressão</h2><p><strong>Carteira:</strong> 85,60 × 53,98 mm (formato ID-1). <strong>Crachá BAMAB:</strong> 90 × 120 mm. Os tamanhos físicos não podem ser alterados pelo editor, evitando impressão deformada. Use escala 100%, tamanho real e nenhuma margem.</p></div></div></section>

<form method="post" class="panel form-stack document-editor-form">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="action" value="save">
<h2>1. Cores</h2>
<div class="doc-color-grid">
<label>Fundo escuro<input type="color" name="doc_primary_color" value="<?=e(setting('doc_primary_color','#0b1118'))?>"></label>
<label>Dourado<input type="color" name="doc_gold_color" value="<?=e(setting('doc_gold_color','#d1a33f'))?>"></label>
<label>Vinho<input type="color" name="doc_wine_color" value="<?=e(setting('doc_wine_color','#7d1020'))?>"></label>
<label>Fundo claro<input type="color" name="doc_light_color" value="<?=e(setting('doc_light_color','#f5f1e9'))?>"></label>
<label>Texto<input type="color" name="doc_text_color" value="<?=e(setting('doc_text_color','#171717'))?>"></label>
</div>

<h2>2. Títulos e rótulos</h2>
<div class="grid2">
<label>Carteirinha do aluno<input name="doc_student_card_title" value="<?=e(setting('doc_student_card_title'))?>"></label>
<label>Crachá do aluno<input name="doc_student_badge_title" value="<?=e(setting('doc_student_badge_title'))?>"></label>
<label>Carteirinha da equipe<input name="doc_team_card_title" value="<?=e(setting('doc_team_card_title'))?>"></label>
<label>Crachá da equipe<input name="doc_team_badge_title" value="<?=e(setting('doc_team_badge_title'))?>"></label><label>Carteirinha do acompanhante<input name="doc_companion_card_title" value="<?=e(setting('doc_companion_card_title'))?>"></label>
<label>Crachá do acompanhante<input name="doc_companion_badge_title" value="<?=e(setting('doc_companion_badge_title'))?>"></label>
<label>Rótulo nome<input name="doc_student_name_label" value="<?=e(setting('doc_student_name_label'))?>"></label>
<label>Rótulo nome social/apelido<input name="doc_preferred_name_label" value="<?=e(setting('doc_preferred_name_label'))?>"></label>
<label>Modo do nome nos documentos do aluno
<select name="doc_student_card_name_mode">
  <option value="first_first_surname" <?=setting('doc_student_card_name_mode','first_last_surname')==='first_first_surname'?'selected':''?>>PRIMEIRO NOME + PRIMEIRO SOBRENOME</option>
  <option value="first_last_surname" <?=setting('doc_student_card_name_mode','first_last_surname')==='first_last_surname'?'selected':''?>>PRIMEIRO NOME + ÚLTIMO SOBRENOME</option>
  <option value="nickname" <?=setting('doc_student_card_name_mode','first_last_surname')==='nickname'?'selected':''?>>APELIDO / NOME SOCIAL</option>
</select>
</label>
<label>Modo do nome nos documentos da equipe
<select name="doc_team_card_name_mode">
  <option value="first_first_surname" <?=setting('doc_team_card_name_mode','first_last_surname')==='first_first_surname'?'selected':''?>>PRIMEIRO NOME + PRIMEIRO SOBRENOME</option>
  <option value="first_last_surname" <?=setting('doc_team_card_name_mode','first_last_surname')==='first_last_surname'?'selected':''?>>PRIMEIRO NOME + ÚLTIMO SOBRENOME</option>
  <option value="nickname" <?=setting('doc_team_card_name_mode','first_last_surname')==='nickname'?'selected':''?>>APELIDO / NOME SOCIAL</option>
</select>
</label>
<label>Rótulo matrícula<input name="doc_number_label" value="<?=e(setting('doc_number_label'))?>"></label>
<label>Rótulo instrumento/ala<input name="doc_role_label" value="<?=e(setting('doc_role_label'))?>"></label>
<label>Rótulo nº equipe<input name="doc_team_number_label" value="<?=e(setting('doc_team_number_label'))?>"></label>
<label>Rótulo função equipe<input name="doc_team_role_label" value="<?=e(setting('doc_team_role_label'))?>"></label><label>Rótulo nº acompanhante<input name="doc_companion_number_label" value="<?=e(setting('doc_companion_number_label'))?>"></label>
<label>Rótulo vínculo acompanhante<input name="doc_companion_role_label" value="<?=e(setting('doc_companion_role_label'))?>"></label>
<label>Rótulo validade<input name="doc_validity_label" value="<?=e(setting('doc_validity_label'))?>"></label>
<label>Rótulo QR<input name="doc_qr_label" value="<?=e(setting('doc_qr_label'))?>"></label>
</div>
<label>Lema<input name="doc_motto" value="<?=e(setting('doc_motto'))?>"></label>
<p class="field-help">O Admin pode escolher separadamente como o nome aparece nos documentos do <strong>aluno</strong> e da <strong>equipe</strong>: primeiro nome + primeiro sobrenome, primeiro nome + último sobrenome ou apelido/nome social. Se o apelido estiver vazio, o sistema usa automaticamente o nome cadastrado.</p>

<h2>3. Tamanho das fontes</h2>
<p class="field-help">Valores em milímetros. O sistema agora limita automaticamente cada campo a uma faixa segura para impedir letras invisíveis, cortes e estouro de layout.</p>
<div class="doc-font-grid">
<label>Título carteirinha<input type="number" step="0.1" min="1" max="8" name="doc_card_title_size" value="<?=e(setting('doc_card_title_size','4.2'))?>"></label>
<label>Nome carteirinha<input type="number" step="0.1" min="1" max="6" name="doc_card_name_size" value="<?=e(setting('doc_card_name_size','2.8'))?>"></label>
<label>Dados carteirinha<input type="number" step="0.1" min="1" max="5" name="doc_card_field_size" value="<?=e(setting('doc_card_field_size','2.2'))?>"></label>
<label>Rótulos carteirinha<input type="number" step="0.1" min="1" max="4" name="doc_card_label_size" value="<?=e(setting('doc_card_label_size','1.65'))?>"></label>
<label>Título crachá<input type="number" step="0.1" min="1" max="8" name="doc_badge_title_size" value="<?=e(setting('doc_badge_title_size','3.8'))?>"></label>
<label>Nome crachá<input type="number" step="0.1" min="1" max="8" name="doc_badge_name_size" value="<?=e(setting('doc_badge_name_size','4.7'))?>"></label>
<label>Dados crachá<input type="number" step="0.1" min="1" max="6" name="doc_badge_field_size" value="<?=e(setting('doc_badge_field_size','2.45'))?>"></label>
<label>Rótulos crachá<input type="number" step="0.1" min="1" max="5" name="doc_badge_label_size" value="<?=e(setting('doc_badge_label_size','2.0'))?>"></label>
<label>Título do verso<input type="number" step="0.1" min="1" max="7" name="doc_back_title_size" value="<?=e(setting('doc_back_title_size','4.0'))?>"></label>
<label>Texto do verso<input type="number" step="0.1" min="1" max="5" name="doc_back_text_size" value="<?=e(setting('doc_back_text_size','1.85'))?>"></label>
</div>

<h2>4. Elementos visíveis</h2>
<div class="doc-toggle-grid">
<label class="check"><input type="checkbox" name="doc_show_preferred_name" <?=setting('doc_show_preferred_name','1')==='1'?'checked':''?>> Mostrar nome social/apelido</label>
<label class="check"><input type="checkbox" name="doc_show_qr_on_front" <?=setting('doc_show_qr_on_front','1')==='1'?'checked':''?>> Mostrar QR na frente</label>
<label class="check"><input type="checkbox" name="doc_show_qr_on_back" <?=setting('doc_show_qr_on_back','1')==='1'?'checked':''?>> Mostrar QR no verso</label>
<label class="check"><input type="checkbox" name="doc_show_motto" <?=setting('doc_show_motto','1')==='1'?'checked':''?>> Mostrar lema</label>
</div>

<h2>5. Textos dos versos</h2>
<label>Verso — carteirinha do aluno<textarea name="card_back_notice" rows="4"><?=e(setting('card_back_notice'))?></textarea></label>
<label>Verso — crachá do aluno<textarea name="badge_back_rules" rows="4"><?=e(setting('badge_back_rules'))?></textarea></label>
<label>Verso — documentos da equipe<textarea name="team_card_back_notice" rows="4"><?=e(setting('team_card_back_notice'))?></textarea></label>
<label>Verso — carteirinha do acompanhante<textarea name="companion_card_back_notice" rows="4"><?=e(setting('companion_card_back_notice'))?></textarea></label>

<div class="doc-editor-actions"><button class="primary doc-save" name="action" value="save">SALVAR E APLICAR EM TODOS OS DOCUMENTOS</button><button class="small-button" name="action" value="reset_safe_fonts" formnovalidate onclick="return confirm('Restaurar somente os tamanhos de fonte para valores seguros?')">RESTAURAR TAMANHOS SEGUROS</button></div>
</form>

<section class="panel doc-safety-panel"><div class="panel-heading-flex"><div><span>PROTEÇÃO AUTOMÁTICA DE LAYOUT</span><h2>Fontes e encaixe corrigidos</h2><p>Nomes e títulos longos agora reduzem automaticamente sem desaparecer. Fotos ausentes usam o brasão como reserva. Datas inválidas exibem “—”. O verso reduz textos longos dentro do espaço disponível e a impressão mantém o tamanho real.</p></div></div></section>
<section class="panel"><h2>Pré-visualização</h2><p>Abra um aluno ou membro aprovado para visualizar os documentos reais preenchidos com os dados cadastrados.</p><div class="document-shortcuts"><a class="small-button" href="matriculas.php">ALUNOS</a><a class="small-button" href="equipe.php">EQUIPE INTERNA</a></div></section>
<section class="panel"><h2>Planilha modelo para relatórios em Excel</h2><p>Baixe uma planilha com o brasão oficial da BAMAB, paleta de cores do site e abas-modelo para relatórios e declarações.</p><div class="document-shortcuts"><?php if(is_file(__DIR__.'/../modelos_excel/BAMAB_MODELO_RELATORIOS_DECLARACOES.xlsx')):?><a class="small-button" href="../modelos_excel/BAMAB_MODELO_RELATORIOS_DECLARACOES.xlsx" download>BAIXAR PLANILHA EXCEL</a><?php else:?><span class="muted">No pacote leve do Modo Grátis, a planilha modelo é opcional. Os relatórios Excel/CSV do sistema continuam funcionando.</span><?php endif;?></div></section>
<?php admin_footer();?>
