/**
 * Dodaje vodeni žig "Sandzak.Net" na sliku prije uploada.
 */
window.applySandzakWatermark = function (file) {
  return new Promise(function (resolve, reject) {
    if (!file || !file.type || !file.type.startsWith("image/")) {
      reject(new Error("Nije slika."));
      return;
    }

    var img = new Image();
    var objectUrl = URL.createObjectURL(file);

    img.onload = function () {
      var canvas = document.createElement("canvas");
      canvas.width = img.naturalWidth;
      canvas.height = img.naturalHeight;
      var ctx = canvas.getContext("2d");
      if (!ctx) {
        URL.revokeObjectURL(objectUrl);
        reject(new Error("Canvas nije podržan."));
        return;
      }

      ctx.drawImage(img, 0, 0);

      var text = "Sandzak.Net";
      var fontSize = Math.max(16, Math.round(canvas.width * 0.032));
      var padding = fontSize * 0.75;

      ctx.font = "bold " + fontSize + "px Inter, Arial, sans-serif";
      ctx.textAlign = "right";
      ctx.textBaseline = "bottom";

      var x = canvas.width - padding;
      var y = canvas.height - padding;

      ctx.shadowColor = "rgba(0,0,0,0.55)";
      ctx.shadowBlur = fontSize * 0.2;
      ctx.shadowOffsetX = 1;
      ctx.shadowOffsetY = 1;
      ctx.fillStyle = "rgba(255,255,255,0.88)";
      ctx.fillText(text, x, y);

      // Diskretan drugi žig po dijagonali na većim slikama
      if (canvas.width > 480 && canvas.height > 320) {
        ctx.save();
        ctx.globalAlpha = 0.14;
        ctx.font = "bold " + Math.round(fontSize * 1.6) + "px Inter, Arial, sans-serif";
        ctx.translate(canvas.width * 0.5, canvas.height * 0.5);
        ctx.rotate(-0.35);
        ctx.textAlign = "center";
        ctx.fillStyle = "#ffffff";
        ctx.shadowColor = "transparent";
        ctx.fillText(text, 0, 0);
        ctx.restore();
      }

      var mime = file.type === "image/png" ? "image/png" : "image/jpeg";
      var quality = mime === "image/jpeg" ? 0.9 : undefined;

      canvas.toBlob(
        function (blob) {
          URL.revokeObjectURL(objectUrl);
          if (!blob) {
            reject(new Error("Žig nije dodan."));
            return;
          }
          resolve(new File([blob], file.name, { type: mime, lastModified: Date.now() }));
        },
        mime,
        quality
      );
    };

    img.onerror = function () {
      URL.revokeObjectURL(objectUrl);
      reject(new Error("Slika se nije učitala."));
    };

    img.src = objectUrl;
  });
};
