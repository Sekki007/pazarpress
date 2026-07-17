/**
 * Kompresuje / smanjuje sliku pre uploada da ne padne na PHP upload_max_filesize (~2M default).
 * Vraća File (JPEG/WebP) ili original (GIF / već dovoljno mali).
 */
(function (global) {
  var DEFAULT_MAX_BYTES = 1.8 * 1024 * 1024;
  var DEFAULT_MAX_EDGE = 1920;

  function extForMime(mime) {
    if (mime === "image/png") return "png";
    if (mime === "image/webp") return "webp";
    if (mime === "image/gif") return "gif";
    return "jpg";
  }

  function renameWithExt(name, ext) {
    var base = String(name || "slika").replace(/\.[^.]+$/, "");
    return base + "." + ext;
  }

  function loadImage(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        resolve(img);
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error("Ne mogu učitati sliku."));
      };
      img.src = url;
    });
  }

  function canvasToBlob(canvas, mime, quality) {
    return new Promise(function (resolve) {
      if (canvas.toBlob) {
        canvas.toBlob(function (blob) {
          resolve(blob);
        }, mime, quality);
        return;
      }
      try {
        var dataUrl = canvas.toDataURL(mime, quality);
        var parts = dataUrl.split(",");
        var bin = atob(parts[1] || "");
        var arr = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
        resolve(new Blob([arr], { type: mime }));
      } catch (e) {
        resolve(null);
      }
    });
  }

  /**
   * @param {File} file
   * @param {{maxBytes?:number,maxEdge?:number,quality?:number}} [opts]
   * @returns {Promise<File>}
   */
  function compressImageForUpload(file, opts) {
    opts = opts || {};
    var maxBytes = opts.maxBytes || DEFAULT_MAX_BYTES;
    var maxEdge = opts.maxEdge || DEFAULT_MAX_EDGE;
    var quality = opts.quality || 0.82;

    if (!file || !file.type || file.type.indexOf("image/") !== 0) {
      return Promise.resolve(file);
    }
    // Ne diraj animirani GIF
    if (file.type === "image/gif") {
      return Promise.resolve(file);
    }

    return loadImage(file).then(function (img) {
      var w = img.naturalWidth || img.width;
      var h = img.naturalHeight || img.height;
      if (!w || !h) return file;

      var scale = Math.min(1, maxEdge / Math.max(w, h));
      var needsResize = scale < 1;
      var needsCompress = file.size > maxBytes;
      if (!needsResize && !needsCompress) {
        return file;
      }

      var cw = Math.max(1, Math.round(w * scale));
      var ch = Math.max(1, Math.round(h * scale));
      var canvas = document.createElement("canvas");
      canvas.width = cw;
      canvas.height = ch;
      var ctx = canvas.getContext("2d");
      if (!ctx) return file;
      ctx.fillStyle = "#ffffff";
      ctx.fillRect(0, 0, cw, ch);
      ctx.drawImage(img, 0, 0, cw, ch);

      // JPEG je najpouzdaniji za cover (manji od PNG)
      var outMime = "image/jpeg";
      var qualities = [quality, 0.72, 0.6, 0.5];

      function tryQuality(i) {
        if (i >= qualities.length) {
          return canvasToBlob(canvas, outMime, 0.45).then(function (blob) {
            if (!blob) return file;
            return new File([blob], renameWithExt(file.name, "jpg"), {
              type: outMime,
              lastModified: Date.now(),
            });
          });
        }
        return canvasToBlob(canvas, outMime, qualities[i]).then(function (blob) {
          if (!blob) return file;
          if (blob.size > maxBytes && i + 1 < qualities.length) {
            return tryQuality(i + 1);
          }
          return new File([blob], renameWithExt(file.name, "jpg"), {
            type: outMime,
            lastModified: Date.now(),
          });
        });
      }

      return tryQuality(0);
    }).catch(function () {
      return file;
    });
  }

  global.compressImageForUpload = compressImageForUpload;
  global.imageExtFromType = extForMime;
})(typeof window !== "undefined" ? window : this);
