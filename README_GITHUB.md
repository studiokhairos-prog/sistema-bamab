# BAMAB — Sistema Oficial

Este repositório contém o sistema BAMAB em PHP.

## Importante
GitHub Pages NÃO executa PHP. Este repositório usa GitHub para versionamento e GitHub Actions para publicar os arquivos em uma hospedagem PHP.

## Publicação automática
Workflow: `.github/workflows/deploy-infinityfree.yml`

Cadastre em Settings > Secrets and variables > Actions os secrets:

- `FTP_HOST`
- `FTP_USERNAME`
- `FTP_PASSWORD`
- `FTP_REMOTE_DIR`

Para InfinityFree, o host normalmente é `ftpupload.net` e a pasta do primeiro domínio costuma ser `/htdocs/`. Em domínio adicional, confirme o caminho mostrado no painel, por exemplo `/seudominio.com/htdocs/`.

## Proteção dos dados
O banco SQLite real e os uploads dos usuários não devem ser enviados ao GitHub. O `.gitignore` já protege esses arquivos.

O deploy NÃO usa opção de apagar arquivos remotos, para preservar banco, fotos, assinaturas, logos e outros uploads já existentes na hospedagem.
