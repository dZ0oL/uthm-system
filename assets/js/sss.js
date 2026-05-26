/**
 * sss.js — Shamir's Secret Sharing (browser-side)
 * Splits the master key into 5 shares during registration.
 * Shares 1 & 2 stay on devices. Shares 3, 4, 5 go to server.
 */
const UTHMSS = (() => {

  const PRIME = 257;

  function mod(a, m) {
    return ((a % m) + m) % m;
  }

  function modPow(base, exp, m) {
    let result = 1n;
    base = BigInt(base) % BigInt(m);
    exp  = BigInt(exp);
    m    = BigInt(m);
    while (exp > 0n) {
      if (exp % 2n === 1n) result = result * base % m;
      exp  = exp / 2n;
      base = base * base % m;
    }
    return Number(result);
  }

  function modInverse(a, p) {
    return modPow(mod(a, p), p - 2, p);
  }

  function evalPoly(coeffs, x, p) {
    let result = 0, xPow = 1;
    for (const c of coeffs) {
      result = mod(result + c * xPow, p);
      xPow   = mod(xPow * x, p);
    }
    return result;
  }

  function lagrange(points, p) {
    let secret = 0;
    for (let i = 0; i < points.length; i++) {
      let num = 1, den = 1;
      for (let j = 0; j < points.length; j++) {
        if (i === j) continue;
        num = mod(num * mod(-points[j].x, p), p);
        den = mod(den * mod(points[i].x - points[j].x, p), p);
      }
      secret = mod(secret + points[i].y * num % p * modInverse(den, p), p);
    }
    return secret;
  }

  /**
   * Split a Uint8Array secret into N shares with threshold K.
   * Returns array of { shareIndex, shareData (hex) }
   */
  function split(secretBytes, n = 5, k = 3) {
    const shareUint16 = Array.from({ length: n }, () => []);

    for (const byte of secretBytes) {
      const coeffs = [byte];
      for (let i = 1; i < k; i++) {
        coeffs.push(crypto.getRandomValues(new Uint8Array(1))[0]);
      }
      for (let idx = 0; idx < n; idx++) {
        shareUint16[idx].push(evalPoly(coeffs, idx + 1, PRIME));
      }
    }

    return shareUint16.map((vals, idx) => {
      const buf = new Uint8Array(vals.length * 2);
      vals.forEach((v, i) => {
        buf[i * 2]     = v & 0xff;
        buf[i * 2 + 1] = (v >> 8) & 0xff;
      });
      return {
        shareIndex: idx + 1,
        shareData:  Array.from(buf).map(b => b.toString(16).padStart(2,'0')).join('')
      };
    });
  }

  /**
   * Reconstruct secret from K shares.
   * @param {Array} shares - [{ shareIndex, shareData }, ...]
   * @returns {Uint8Array}
   */
  function reconstruct(shares) {
    const arrays = shares.map(s => {
      const buf  = s.shareData.match(/.{2}/g).map(h => parseInt(h, 16));
      const vals = [];
      for (let i = 0; i < buf.length; i += 2) {
        vals.push(buf[i] | (buf[i + 1] << 8));
      }
      return { x: s.shareIndex, vals };
    });

    const len     = arrays[0].vals.length;
    const secret  = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
      const points = arrays.map(a => ({ x: a.x, y: a.vals[i] }));
      secret[i]    = lagrange(points, PRIME);
    }
    return secret;
  }

  return { split, reconstruct };
})();
