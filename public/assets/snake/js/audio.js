// js/audio.js
import { getGameConfig, getSettings } from "./main.js";

let audioCtx = null;
const audioBuffers = {};
const sfxKeys = ["up", "down", "left", "right"];

function chooseFormat(base) {
  const { audioBaseUrl } = getGameConfig();
  const normalizedBaseUrl = audioBaseUrl.replace(/\/$/, "");
  const a = document.createElement("audio");
  // Prefer m4a on iOS; ogg elsewhere if supported
  if (a.canPlayType('audio/mp4; codecs="mp4a.40.2"')) return `${normalizedBaseUrl}/${base}.m4a`;
  if (a.canPlayType('audio/ogg; codecs="vorbis"')) return `${normalizedBaseUrl}/${base}.ogg`;
  return `${normalizedBaseUrl}/${base}.m4a`; // safe default
}

export function initAudio() {
  if (audioCtx) return;
  audioCtx = new (window.AudioContext || window.webkitAudioContext)();

  const url = chooseFormat("snake_movement");
  // Preload once (same file for all directions today)
  fetch(url)
    .then(r => r.arrayBuffer())
    .then(buf => audioCtx.decodeAudioData(buf))
    .then(decoded => {
      sfxKeys.forEach(k => (audioBuffers[k] = decoded));
    });
}
// js/audio.js
export function ensureAudioReady() {
  if (audioCtx && (audioCtx.state === 'suspended' || audioCtx.state === 'interrupted')) {
    audioCtx.resume();
  }
}

export function playSound(direction) {
  if (!audioCtx || !audioBuffers[direction]) return;
  const { masterVolume, sfxVolume } = getSettings();
  const source = audioCtx.createBufferSource();
  source.buffer = audioBuffers[direction];
  const gain = audioCtx.createGain();
  gain.gain.value = masterVolume * sfxVolume;
  source.connect(gain).connect(audioCtx.destination);
  source.start(0);
}
