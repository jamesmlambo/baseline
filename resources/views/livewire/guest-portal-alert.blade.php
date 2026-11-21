<div @if ($enabled) wire:poll.15s="check" @endif
     x-data
     x-on:guest-portal-chime.window="
        try {
            const audio = new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.25].forEach((delay) => {
                const tone = audio.createOscillator();
                const volume = audio.createGain();
                tone.frequency.value = 880;
                volume.gain.setValueAtTime(0.2, audio.currentTime + delay);
                volume.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + delay + 0.2);
                tone.connect(volume).connect(audio.destination);
                tone.start(audio.currentTime + delay);
                tone.stop(audio.currentTime + delay + 0.2);
            });
        } catch (error) {}
     "
     aria-hidden="true"></div>
