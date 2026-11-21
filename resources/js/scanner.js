import jsQR from 'jsqr';

/**
 * Camera QR reader for the reception scan page (REC-06). Frames are decoded locally in the
 * browser with jsQR; nothing is sent anywhere until a code is found.
 */
window.BaselineScanner = {
    async start(video, canvas, onResult) {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('This browser cannot use the camera. Use a USB scanner or type the code.');
        }

        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        video.srcObject = stream;
        video.setAttribute('playsinline', 'true');
        await video.play();

        const context = canvas.getContext('2d', { willReadFrequently: true });
        let stopped = false;

        const stop = () => {
            stopped = true;
            stream.getTracks().forEach((track) => track.stop());
        };

        const tick = () => {
            if (stopped) {
                return;
            }

            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const image = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });

                if (code?.data) {
                    stop();
                    onResult(code.data);

                    return;
                }
            }

            requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);

        return stop;
    },
};
