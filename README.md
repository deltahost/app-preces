# Preces e Orações

MVP mobile em Expo/React Native para leitura, criação, narração e compartilhamento de orações.

## Executar

```bash
npm install
npx expo start
```

Abra no Expo Go para testar em um celular. Para gerar versões de loja, configure uma conta Expo e use EAS Build.

## O que já funciona no protótipo

- Home devocional com versículo por dia da semana, Bíblia, categorias, prece e inspiração do dia.
- Cinco áreas no rodapé: Comentar/Home, Vídeos, Minhas orações, Favoritos e Pai Nosso.
- Criação, edição, exclusão e favoritos de orações com persistência local.
- Narração local em português, com variações masculina e feminina simuladas por pitch.
- Pai Nosso com narração gratuita.
- Menu lateral, tema claro/escuro, lembrete, horário e links institucionais preparados.
- Página de venda Premium com planos mensal e anual, benefícios, restauração e cancelamento.
- Bloqueios visuais de recursos Premium e estado de assinatura simulado.

## Vozes de narração

Os perfis definidos no aplicativo são:

- Masculina: `fr-FR-RemyMultilingualNeural`, português brasileiro, prosódia narrativa em `-10%`.
- Feminina: `pt-BR-ThalitaMultilingualNeural`, português brasileiro, prosódia narrativa em `-10%`.

Para testar as vozes reais pelo serviço online do Edge, instale a dependência uma vez e inicie o serviço local. Não é necessária chave:

```powershell
npm run tts:setup
npm run tts:server
```

Antes de iniciar o Expo, defina o endereço do computador acessível pelo celular:

```powershell
$env:EXPO_PUBLIC_TTS_API_URL="http://192.168.0.10:8787"
npx expo start --clear
```

Troque `192.168.0.10` pelo IPv4 do computador. Sem essa configuração, o app utiliza a voz TTS instalada no aparelho como alternativa de desenvolvimento.

### Publicar o TTS no Render

O arquivo `render.yaml` configura um Web Service Python com verificação em `/health`.

1. Envie o projeto para um repositório Git compatível com o Render.
2. No Render, escolha **New > Blueprint** e conecte o repositório.
3. Aguarde o serviço `preces-edge-tts` ficar disponível.
4. Copie a URL HTTPS fornecida pelo Render.
5. Configure `EXPO_PUBLIC_TTS_API_URL=https://seu-servico.onrender.com` antes de iniciar ou gerar o app.

O token opcional `TTS_API_TOKEN` serve apenas como barreira durante testes. Se ele for usado diretamente pelo aplicativo através de `EXPO_PUBLIC_TTS_API_TOKEN`, poderá ser extraído do APK. Na produção, a API principal deverá validar usuário e assinatura antes de autorizar a geração Premium.

O plano gratuito do Render usa armazenamento temporário e pode desligar após inatividade. Por isso, o servidor reaproveita áudios iguais enquanto o arquivo existir, mas mantém a voz local do aparelho como alternativa.

## Integrações para produção

O protótipo usa `expo-speech` como alternativa local. O serviço sem chave está em `server/edge_tts_server.py`. Para uma futura operação comercial com SLA oficial, há também um adaptador Azure opcional em `server/tts-server.mjs`:

1. O app envia texto, idioma e voz para `POST /v1/tts`.
2. O backend valida usuário, assinatura, tamanho do texto e limite diário.
3. O backend gera ou recupera do cache o áudio usando as vozes online do Edge.
4. O app recebe uma URL temporária e reproduz/baixa o arquivo apenas quando o plano permitir.

Também faltam as credenciais e contas externas para concluir:

- Compras reais: RevenueCat ou StoreKit/Google Play Billing.
- Anúncios: Google AdMob com consentimento e limite de frequência para intersticiais.
- Painel de conteúdo: Supabase ou Firebase para categorias, preces, vídeos e áudios.
- IA bíblica: endpoint com moderação, limites e base bíblica licenciada.
- Notificações: agendamento local com `expo-notifications` e seletor de horário.
- Compartilhamento: geração da imagem e chamada à folha nativa do sistema.
- Termos, política de privacidade e licença final fornecidos pelo responsável pelo app.

## Política de anúncios recomendada

Não exiba intersticial em todo toque. Use pontos naturais (por exemplo, após concluir uma leitura ou a cada algumas aberturas), limite por sessão/tempo e nunca interrompa escrita, compra ou oração em reprodução.
