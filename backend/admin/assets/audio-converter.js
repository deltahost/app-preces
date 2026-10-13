(function () {
  'use strict';

  const CORE_BASE_URL = 'https://cdn.jsdelivr.net/npm/@ffmpeg/core@0.12.10/dist/umd';
  let ffmpegPromise;

  function formatBytes(bytes) {
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  }

  async function remoteBlobUrl(url, mimeType) {
    const response = await fetch(url);
    if (!response.ok) throw new Error('Não foi possível carregar o conversor.');
    const data = await response.arrayBuffer();
    return URL.createObjectURL(new Blob([data], { type: mimeType }));
  }

  async function loadFFmpeg(status) {
    if (!window.FFmpegWASM?.FFmpeg) throw new Error('Conversor WebAssembly indisponível.');
    if (!ffmpegPromise) {
      ffmpegPromise = (async () => {
        status.textContent = 'Carregando o conversor pela primeira vez (aprox. 32 MB)…';
        const ffmpeg = new window.FFmpegWASM.FFmpeg();
        await ffmpeg.load({
          coreURL: await remoteBlobUrl(`${CORE_BASE_URL}/ffmpeg-core.js`, 'text/javascript'),
          wasmURL: await remoteBlobUrl(`${CORE_BASE_URL}/ffmpeg-core.wasm`, 'application/wasm'),
        });
        return ffmpeg;
      })().catch(error => {
        ffmpegPromise = undefined;
        throw error;
      });
    }
    return ffmpegPromise;
  }

  function safeBaseName(name) {
    const withoutExtension = name.replace(/\.[^.]+$/, '');
    return withoutExtension.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'audio';
  }

  async function convert(container) {
    const input = container.querySelector('[data-audio-file]');
    const quality = container.querySelector('[data-audio-quality]');
    const button = container.querySelector('[data-audio-convert]');
    const status = container.querySelector('[data-audio-status]');
    const source = input.files?.[0];
    if (!source) {
      status.textContent = 'Escolha um arquivo de áudio primeiro.';
      return;
    }
    if (source.size > 150 * 1024 * 1024) {
      status.textContent = 'O arquivo original deve ter no máximo 150 MB.';
      return;
    }

    button.disabled = true;
    input.disabled = true;
    try {
      const ffmpeg = await loadFFmpeg(status);
      const extension = source.name.split('.').pop()?.replace(/[^a-zA-Z0-9]/g, '') || 'audio';
      const stamp = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
      const inputName = `entrada-${stamp}.${extension}`;
      const outputName = `saida-${stamp}.mp3`;
      status.textContent = 'Convertendo e comprimindo… não feche esta página.';
      await ffmpeg.writeFile(inputName, new Uint8Array(await source.arrayBuffer()));
      const exitCode = await ffmpeg.exec(['-i', inputName, '-vn', '-map_metadata', '-1', '-ac', '1', '-ar', '24000', '-codec:a', 'libmp3lame', '-b:a', quality.value, outputName]);
      if (exitCode !== 0) throw new Error('O formato informado não pôde ser convertido.');
      const result = await ffmpeg.readFile(outputName);
      if (!(result instanceof Uint8Array)) throw new Error('O conversor não retornou um áudio válido.');
      const converted = new File([result], `${safeBaseName(source.name)}.mp3`, { type: 'audio/mpeg' });
      const transfer = new DataTransfer();
      transfer.items.add(converted);
      input.files = transfer.files;
      status.textContent = `Pronto: ${formatBytes(source.size)} → ${formatBytes(converted.size)} em MP3 ${quality.value}. Agora salve a oração.`;
      await ffmpeg.deleteFile(inputName).catch(() => {});
      await ffmpeg.deleteFile(outputName).catch(() => {});
    } catch (error) {
      status.textContent = error instanceof Error ? error.message : 'Não foi possível converter o áudio.';
    } finally {
      input.disabled = false;
      button.disabled = false;
    }
  }

  document.querySelectorAll('[data-audio-converter]').forEach(container => {
    const button = container.querySelector('[data-audio-convert]');
    button?.addEventListener('click', () => void convert(container));
  });
})();
