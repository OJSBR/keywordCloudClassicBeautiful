# Contributing

Thanks for your interest in improving this plugin! It is maintained by
**[OJSBR](https://ojsbr.com)** and released under the **GNU GPL v3** so the whole
PKP community can use and improve it.

## Reporting issues

- Open an issue describing the problem or suggestion.
- Include your **OJS version**, the **plugin version** (see `version.xml`), and steps
  to reproduce. Errors from `error_log` / the browser console help a lot.

## Branch model

| Branch | Target |
|--------|--------|
| `stable-3_5_0` *(default)* | OJS 3.5.x |

Base your work on — and open your pull request against — the `stable-3_5_0` branch.

## Pull requests

1. Fork the repository and create a topic branch from `stable-3_5_0`.
2. Keep the repository layout intact: the repo root **is** the plugin folder (so
   `version.xml` stays at the root, and the folder installs into `plugins/blocks/`).
3. Follow the existing code style and the
   [PKP coding conventions](https://docs.pkp.sfu.ca/dev/documentation/en/coding). The plugin
   uses namespaced, PSR-4 classes (`APP\plugins\blocks\keywordCloudClassicBeautiful`).
4. Add/keep translation strings in `locale/<lang>/locale.po`. The plugin ships **7 locales**
   (`en`, `pt_BR`, `pt`, `es`, `fr_FR`, `it`, `de`) — keep them in sync when you add a key.
5. The word-cloud layout library in `js/wordcloud2.js` is a **vendored** copy of
   [wordcloud2.js](https://github.com/timdream/wordcloud2.js) (MIT) with one small change,
   marked `/* OJSBR patch */`. If you update it, re-apply that patch and keep the MIT header.
6. When your change is user-visible, bump `<release>` and `<date>` in `version.xml`.
7. Describe **what** and **why** in the PR, and mention which OJS version you tested on.

By submitting a contribution you agree to license it under the **GNU GPL v3**, consistent
with this project.

---

## 🇧🇷 Português

Obrigado pelo interesse em melhorar este plugin! Ele é mantido pela
**[OJSBR](https://ojsbr.com)** e distribuído sob a **GNU GPL v3**, para que toda a
comunidade PKP possa usar e evoluir.

### Relatando problemas

- Abra uma *issue* descrevendo o problema ou a sugestão.
- Informe a **versão do OJS**, a **versão do plugin** (veja `version.xml`) e o passo a
  passo para reproduzir. Mensagens do `error_log` / console do navegador ajudam muito.

### Modelo de branches

A branch `stable-3_5_0` (padrão) mira o OJS 3.5.x. Baseie seu trabalho — e abra o pull
request — nessa branch.

### Pull requests

1. Faça um *fork* e crie uma branch de trabalho a partir da `stable-3_5_0`.
2. Mantenha o layout do repositório: a raiz do repo **é** a pasta do plugin (o `version.xml`
   fica na raiz, e a pasta instala em `plugins/blocks/`).
3. Siga o estilo do código e as
   [convenções de código da PKP](https://docs.pkp.sfu.ca/dev/documentation/en/coding). O plugin
   usa classes com namespace, PSR-4 (`APP\plugins\blocks\keywordCloudClassicBeautiful`).
4. Mantenha as strings em `locale/<idioma>/locale.po`. O plugin traz **7 locales**
   (`en`, `pt_BR`, `pt`, `es`, `fr_FR`, `it`, `de`) — mantenha-os em dia ao adicionar chaves.
5. A biblioteca em `js/wordcloud2.js` é uma cópia **embarcada** do
   [wordcloud2.js](https://github.com/timdream/wordcloud2.js) (MIT) com uma pequena alteração,
   marcada `/* OJSBR patch */`. Ao atualizá-la, reaplique o patch e mantenha o cabeçalho MIT.
6. Em mudanças visíveis ao usuário, incremente `<release>` e `<date>` no `version.xml`.
7. Explique **o quê** e **por quê** no PR, e diga em qual versão do OJS testou.

Ao enviar uma contribuição, você concorda em licenciá-la sob a **GNU GPL v3**, coerente com
este projeto.
