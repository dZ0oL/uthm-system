/**
 * sss.js — Shamir's Secret Sharing (browser-side)
 * Splits the master key into 5 shares during registration.
 * Any 3 of the 5 shares can reconstruct the secret (3-of-5 threshold).
 * Uses GF(257) — a finite field whose prime is just above 256,
 * so every byte value 0–255 is a valid field element.
 *
 * Share distribution:
 *   Share 1 → browser IndexedDB (this device)
 *   Share 2 → server sss_shares_secondary table
 *   Share 3 → secure DB admin_vault_shares table
 *   Share 4 → secure DB backup_shares table
 *   Share 5 → main DB sss_shares table
 */
const UTHMSS = (() => {

  // 257 is the smallest prime > 256, so every byte value fits in GF(257)
  const PRIME = 257;

  // True modulo (JS % can return negative for negative operands)
  function mod(a, m) {
    return ((a % m) + m) % m;
  }

  // Fast modular exponentiation using BigInt (avoids overflow for large numbers)
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

  // Modular multiplicative inverse using Fermat's little theorem: a^(p-2) mod p
  function modInverse(a, p) {
    return modPow(mod(a, p), p - 2, p);
  }

  // Evaluate a polynomial with given coefficients at x (mod p)
  // coeffs[0] is the secret (constant term), coeffs[1..k-1] are random
  function evalPoly(coeffs, x, p) {
    let result = 0, xPow = 1;
    for (const c of coeffs) {
      result = mod(result + c * xPow, p);
      xPow   = mod(xPow * x, p);
    }
    return result;
  }

  // Lagrange interpolation: given K (x,y) points, recover the secret (f(0) mod p)
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
   * Each byte is shared independently using a degree-(k-1) polynomial.
   * Share values can exceed 255 (up to 256 in GF(257)), so each value
   * is stored as 2 bytes (uint16, little-endian) in the share hex string.
   * Returns array of { shareIndex, shareData (hex) }
   */
  function split(secretBytes, n = 5, k = 3) {
    const shareUint16 = Array.from({ length: n }, () => []);

    for (const byte of secretBytes) {
      // Random degree-(k-1) polynomial with f(0) = byte
      const coeffs = [byte];
      for (let i = 1; i < k; i++) {
        coeffs.push(crypto.getRandomValues(new Uint8Array(1))[0]);
      }
      // Evaluate at x=1..n to generate each share's contribution for this byte
      for (let idx = 0; idx < n; idx++) {
        shareUint16[idx].push(evalPoly(coeffs, idx + 1, PRIME));
      }
    }

    // Pack each share's uint16 values into a hex string (2 bytes per value)
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
   * Unpacks each share's hex string back to uint16 values,
   * then applies Lagrange interpolation byte-by-byte.
   * @param {Array} shares - [{ shareIndex, shareData }, ...]
   * @returns {Uint8Array}
   */
  function reconstruct(shares) {
    const arrays = shares.map(s => {
      const buf  = s.shareData.match(/.{2}/g).map(h => parseInt(h, 16));
      const vals = [];
      // Unpack little-endian uint16 values
      for (let i = 0; i < buf.length; i += 2) {
        vals.push(buf[i] | (buf[i + 1] << 8));
      }
      return { x: s.shareIndex, vals };
    });

    const len     = arrays[0].vals.length;
    const secret  = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
      // For each byte position, gather the (x, y) points from all provided shares
      const points = arrays.map(a => ({ x: a.x, y: a.vals[i] }));
      secret[i]    = lagrange(points, PRIME);
    }
    return secret;
  }

  return { split, reconstruct };
})();
