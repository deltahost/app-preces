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
    const progressTrack = container.querySelector('[data-audio-progress]');
    const progressBar = progressTrack?.querySelector('span');
    const source = input.files?.[0];
    if (!source) {
      status.textContent = 'Escolha um arquivo de áudio primeiro.';
      return;
    }
    if (source.size <= 0) {
      status.textContent = 'O arquivo selecionado está vazio. Escolha outro áudio.';
      return;
    }
    if (source.size > 150 * 1024 * 1024) {
      status.textContent = 'O arquivo original deve ter no máximo 150 MB.';
      return;
    }

    button.disabled = true;
    input.disabled = true;
    container.dataset.processing = 'true';
    if (progressTrack && progressBar) {
      progressTrack.hidden = false;
      progressBar.style.width = '5%';
    }
    let progressHandler;
    try {
      const ffmpeg = await loadFFmpeg(status);
      const extension = source.name.split('.').pop()?.replace(/[^a-zA-Z0-9]/g, '') || 'audio';
      const stamp = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
      const inputName = `entrada-${stamp}.${extension}`;
      const outputName = `saida-${stamp}.mp3`;
      status.textContent = 'Convertendo e comprimindo… não feche esta página.';
      progressHandler = ({ progress }) => {
        const percent = Math.max(1, Math.min(99, Math.round(progress * 100)));
        if (progressBar) progressBar.style.width = `${percent}%`;
        status.textContent = `Convertendo e comprimindo… ${percent}%`;
      };
      ffmpeg.on('progress', progressHandler);
      await ffmpeg.writeFile(inputName, new Uint8Array(await source.arrayBuffer()));
      const exitCode = await ffmpeg.exec(['-i', inputName, '-vn', '-map_metadata', '-1', '-ac', '1', '-ar', '24000', '-codec:a', 'libmp3lame', '-b:a', quality.value, outputName]);
      if (exitCode !== 0) throw new Error('O formato informado não pôde ser convertido.');
      const result = await ffmpeg.readFile(outputName);
      if (!(result instanceof Uint8Array)) throw new Error('O conversor não retornou um áudio válido.');
      const converted = new File([result], `${safeBaseName(source.name)}.mp3`, { type: 'audio/mpeg' });
      if (converted.size <= 0) throw new Error('A conversão gerou um arquivo vazio. Tente novamente com o áudio original.');
      if (converted.size > 25 * 1024 * 1024) throw new Error('O MP3 ainda ficou acima de 25 MB. Escolha 48 kbps e converta novamente.');
      const transfer = new DataTransfer();
      transfer.items.add(converted);
      input.files = transfer.files;
      input.dataset.converted = 'true';
      if (progressBar) progressBar.style.width = '100%';
      status.textContent = `Pronto: ${formatBytes(source.size)} → ${formatBytes(converted.size)} em MP3 ${quality.value}. Agora salve a oração.`;
      await ffmpeg.deleteFile(inputName).catch(() => {});
      await ffmpeg.deleteFile(outputName).catch(() => {});
    } catch (error) {
      if (progressTrack) progressTrack.hidden = true;
      status.textContent = error instanceof Error ? error.message : 'Não foi possível converter o áudio.';
    } finally {
      const ffmpeg = await ffmpegPromise?.catch(() => null);
      if (ffmpeg && progressHandler) ffmpeg.off('progress', progressHandler);
      input.disabled = false;
      button.disabled = false;
      container.dataset.processing = 'false';
    }
  }

  document.querySelectorAll('[data-audio-converter]').forEach(container => {
    const button = container.querySelector('[data-audio-convert]');
    const input = container.querySelector('[data-audio-file]');
    const status = container.querySelector('[data-audio-status]');
    const form = container.closest('form');
    button?.addEventListener('click', () => void convert(container));
    input?.addEventListener('change', () => {
      input.dataset.converted = 'false';
      const file = input.files?.[0];
      if (!file) return;
      status.textContent = `${file.name} selecionado (${formatBytes(file.size)}). Clique em “Converter e comprimir para MP3” antes de salvar.`;
    });
    form?.addEventListener('submit', event => {
      const file = input?.files?.[0];
      if (container.dataset.processing === 'true') {
        event.preventDefault();
        status.textContent = 'Aguarde a conversão terminar antes de salvar.';
        container.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      if (file && file.size <= 0) {
        event.preventDefault();
        status.textContent = 'O áudio está vazio. Escolha ou converta o arquivo novamente.';
        container.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      const isMp3 = file?.name.toLowerCase().endsWith('.mp3');
      if (file && input.dataset.converted !== 'true' && (!isMp3 || file.size > 25 * 1024 * 1024)) {
        event.preventDefault();
        status.textContent = 'Converta o arquivo para MP3 antes de salvar.';
        container.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  });
})();
