<?php

declare(strict_types=1);

namespace OCA\Olvid\Crypto;

use Exception;
use GMP;

/**
 * Twisted Edwards curve arithmetic used by Olvid EC-SDSA signature verification.
 *
 * Curve equation: x² + y² = 1 + d·x²·y²  (over F_p)
 *
 * All coordinates are GMP integers in [0, p-1].  Points are represented as
 * two-element arrays [\GMP $x, \GMP $y]; $x may be null when only $y is known
 * (compact public-key format stores only the Y coordinate).
 *
 * PHP's GMP extension is required.  gmp_mod() always returns a non-negative
 * result when the modulus is positive, which we rely on throughout.
 */
abstract class EdwardCurve {
    public GMP $p;         // field prime
    public GMP $q;         // subgroup order
    public GMP $d;         // curve constant
    public GMP $gx;        // generator X
    public GMP $gy;        // generator Y
    public int  $byteLength; // 32 for both MDC and Curve25519

    // Tonelli–Shanks parameters for modular square root
    protected GMP $tsNonQR;
    protected GMP $tsT;
    protected int  $tsS;

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function mdc(): self       { return MdcCurve::instance(); }
    public static function curve25519(): self { return Curve25519Curve::instance(); }

    // -------------------------------------------------------------------------
    // Point arithmetic
    // -------------------------------------------------------------------------

    /**
     * Twisted Edwards addition:
     *   t   = d·x₁·x₂·y₁·y₂  mod p
     *   X₃  = (x₁y₂ + y₁x₂) / (1 + t)   mod p
     *   Y₃  = (y₁y₂ - x₁x₂) / (1 − t)   mod p
     *
     * @return array{0:GMP,1:GMP}
     * @throws Exception if a denominator has no modular inverse (degenerate input)
     */
    public function pointAdd(GMP $x1, GMP $y1, GMP $x2, GMP $y2): array {
        $p = $this->p;
        $t = gmp_mod(gmp_mul(gmp_mul(gmp_mul(gmp_mul($this->d, $x1), $x2), $y1), $y2), $p);

        $inv1 = gmp_invert(gmp_mod(gmp_add($t, 1), $p), $p);
        $inv2 = gmp_invert(gmp_mod(gmp_sub(1, $t), $p), $p);
        if ($inv1 === false || $inv2 === false) {
            throw new Exception('No modular inverse in pointAdd (degenerate point)');
        }

        $x3 = gmp_mod(gmp_mul($inv1, gmp_mod(gmp_add(gmp_mul($x1, $y2), gmp_mul($y1, $x2)), $p)), $p);
        $y3 = gmp_mod(gmp_mul($inv2, gmp_mod(gmp_sub(gmp_mul($y1, $y2), gmp_mul($x1, $x2)), $p)), $p);
        return [$x3, $y3];
    }

    /**
     * Scalar multiplication n·P using the Montgomery ladder with full (X,Y) tracking.
     *
     * @return array{0:GMP,1:GMP}
     */
    public function scalarMulXY(GMP $n, GMP $px, GMP $py): array {
        $ONE   = gmp_init(1);
        $ZERO  = gmp_init(0);
        $pMin1 = gmp_sub($this->p, $ONE);

        if (gmp_cmp($n, 0) === 0 || gmp_cmp($py, $ONE) === 0) {
            return [$ZERO, $ONE];
        }
        if (gmp_cmp($py, $pMin1) === 0) {
            return gmp_testbit($n, 0) ? [$ZERO, $pMin1] : [$ZERO, $ONE];
        }

        $rx = $ZERO; $ry = $ONE;  // R = identity
        $qx = $px;   $qy = $py;  // Q = P

        for ($i = strlen(gmp_strval($n, 2)) - 1; $i >= 0; $i--) {
            if (gmp_testbit($n, $i)) {
                [$rx, $ry] = $this->pointAdd($rx, $ry, $qx, $qy);
                [$qx, $qy] = $this->pointAdd($qx, $qy, $qx, $qy);
            } else {
                [$qx, $qy] = $this->pointAdd($rx, $ry, $qx, $qy);
                [$rx, $ry] = $this->pointAdd($rx, $ry, $rx, $ry);
            }
        }
        return [$rx, $ry];
    }

    /**
     * Y-coordinate-only scalar multiplication using the Montgomery Y-ladder.
     * Returns the Y coordinate of n·P where P is given only by its Y coordinate.
     *
     * Uses projective coordinates (u, w) with y = (u−w)/(u+w).
     */
    public function scalarMulY(GMP $n, GMP $y): GMP {
        $p    = $this->p;
        $ONE  = gmp_init(1);
        $ZERO = gmp_init(0);
        $pMin1 = gmp_sub($p, $ONE);

        if (gmp_cmp($n, 0) === 0 || gmp_cmp($y, $ONE) === 0) {
            return $ONE;
        }
        if (gmp_cmp($y, $pMin1) === 0) {
            return gmp_testbit($n, 0) ? $pMin1 : $ONE;
        }

        // c = (1−d)⁻¹ mod p
        $c = gmp_invert(gmp_mod(gmp_sub($ONE, $this->d), $p), $p);

        $uP = gmp_mod(gmp_add($y, $ONE), $p);
        $wP = gmp_mod(gmp_sub($ONE, $y), $p);

        $uQ = $ONE; $wQ = $ZERO;  // Q = identity
        $uR = $uP;  $wR = $wP;   // R = P

        for ($i = strlen(gmp_strval($n, 2)) - 1; $i >= 0; $i--) {
            // Compute Q+R (whose difference Q−R = P is fixed)
            $t1  = gmp_mod(gmp_mul(gmp_sub($uQ, $wQ), gmp_add($uR, $wR)), $p);
            $t2  = gmp_mod(gmp_mul(gmp_add($uQ, $wQ), gmp_sub($uR, $wR)), $p);
            $sum = gmp_add($t1, $t2);
            $dif = gmp_sub($t1, $t2);
            $uQR = gmp_mod(gmp_mul($wP, gmp_mod(gmp_mul($sum, $sum), $p)), $p);
            $wQR = gmp_mod(gmp_mul($uP, gmp_mod(gmp_mul($dif, $dif), $p)), $p);

            if (gmp_testbit($n, $i)) {
                // Double R; Q ← Q+R
                $t3  = gmp_mod(gmp_mul(gmp_add($uR, $wR), gmp_add($uR, $wR)), $p);
                $t4  = gmp_mod(gmp_mul(gmp_sub($uR, $wR), gmp_sub($uR, $wR)), $p);
                $t5  = gmp_sub($t3, $t4);
                $uQ  = $uQR;
                $wQ  = $wQR;
                $uR  = gmp_mod(gmp_mul($t3, $t4), $p);
                $wR  = gmp_mod(gmp_mul($t5, gmp_add($t4, gmp_mod(gmp_mul($c, $t5), $p))), $p);
            } else {
                // Double Q; R ← Q+R
                $t3  = gmp_mod(gmp_mul(gmp_add($uQ, $wQ), gmp_add($uQ, $wQ)), $p);
                $t4  = gmp_mod(gmp_mul(gmp_sub($uQ, $wQ), gmp_sub($uQ, $wQ)), $p);
                $t5  = gmp_sub($t3, $t4);
                $uR  = $uQR;
                $wR  = $wQR;
                $uQ  = gmp_mod(gmp_mul($t3, $t4), $p);
                $wQ  = gmp_mod(gmp_mul($t5, gmp_add($t4, gmp_mod(gmp_mul($c, $t5), $p))), $p);
            }
        }

        $inv = gmp_invert(gmp_mod(gmp_add($uQ, $wQ), $p), $p);
        if ($inv === false) {
            throw new Exception('No modular inverse in scalarMulY');
        }
        return gmp_mod(gmp_mul(gmp_mod(gmp_sub($uQ, $wQ), $p), $inv), $p);
    }

    /**
     * Recover the X coordinate from Y.  Returns null when Y is not on the curve
     * (no valid square root exists).
     */
    public function xFromY(GMP $y): ?GMP {
        $p  = $this->p;
        $y2 = gmp_mod(gmp_mul($y, $y), $p);
        $num = gmp_mod(gmp_sub(1, $y2), $p);
        $den = gmp_mod(gmp_sub(1, gmp_mod(gmp_mul($this->d, $y2), $p)), $p);
        $inv = gmp_invert($den, $p);
        if ($inv === false) {
            return null;
        }
        return $this->modSqrt(gmp_mod(gmp_mul($num, $inv), $p));
    }

    /**
     * Compute a·G + e·A where A is known only by its Y coordinate (compact key).
     * Returns 0, 1, or 2 candidate points.
     *
     * @return array<array{0:GMP,1:GMP}>
     */
    public function mulAdd(GMP $a, GMP $e, GMP $ay): array {
        [$p3x, $p3y] = $this->scalarMulXY($a, $this->gx, $this->gy);

        $y4 = $this->scalarMulY($e, $ay);
        $x4 = $this->xFromY($y4);
        if ($x4 === null) {
            return [];
        }

        $results = [];
        $results[] = $this->pointAdd($p3x, $p3y, $x4, $y4);
        $x4neg = gmp_mod(gmp_sub($this->p, $x4), $this->p);
        $results[] = $this->pointAdd($p3x, $p3y, $x4neg, $y4);
        return $results;
    }

    /**
     * Encode a GMP integer as a big-endian byte string of exactly $this->byteLength bytes.
     */
    public function gmpToBytes(GMP $n): string {
        return hex2bin(str_pad(gmp_strval($n, 16), $this->byteLength * 2, '0', STR_PAD_LEFT));
    }

    // -------------------------------------------------------------------------
    // Modular square root (Tonelli–Shanks)
    // -------------------------------------------------------------------------

    private function modSqrt(GMP $x): ?GMP {
        $p = $this->p;

        // Legendre symbol must be 1 for a square root to exist
        if (gmp_cmp(gmp_powm($x, gmp_div(gmp_sub($p, 1), 2), $p), 1) !== 0) {
            return null;
        }

        // p ≡ 3 (mod 4): sqrt = x^((p+1)/4) mod p
        if (gmp_testbit($p, 1)) {
            return gmp_powm($x, gmp_div(gmp_add($p, 1), 4), $p);
        }

        // General Tonelli–Shanks (used for Curve25519 where p ≡ 1 mod 4)
        $e = gmp_init(0);
        for ($i = 1; $i < $this->tsS; $i++) {
            // Check (nonQR^e · x)^((p-1)/2^(i+1)) mod p == 1
            $base = gmp_mod(gmp_mul(gmp_powm($this->tsNonQR, $e, $p), $x), $p);
            $exp  = gmp_div(gmp_sub($p, 1), gmp_pow(2, $i + 1));
            if (gmp_cmp(gmp_powm($base, $exp, $p), 1) !== 0) {
                $e = gmp_add($e, gmp_pow(2, $i));
            }
        }
        $part1 = gmp_powm($this->tsNonQR, gmp_div(gmp_mul($this->tsT, $e), 2), $p);
        $part2 = gmp_powm($x, gmp_div(gmp_add($this->tsT, 1), 2), $p);
        return gmp_mod(gmp_mul($part1, $part2), $p);
    }
}

// -----------------------------------------------------------------------------
// Concrete curve: MDC (p ≡ 3 mod 4, simple sqrt)
// -----------------------------------------------------------------------------

final class MdcCurve extends EdwardCurve {
    private static ?self $instance = null;

    private function __construct() {
        $h = fn(string $hex): GMP => gmp_init($hex, 16);
        $this->byteLength = 32;
        $this->p  = $h('f13b68b9d456afb4532f92fdd7a5fd4f086a9037ef07af9ec13710405779ec13');
        $this->q  = $h('3c4eda2e7515abed14cbe4bf75e97f534fb38975faf974bb588552f421b0f7fb');
        $this->d  = $h('571304521965b68a7cdfbfccfb0cb9625f1270f63f21f041ee9309250300cf89');
        $this->gx = $h('b681886a7f903b83d85b421e03cbcf6350d72abb8d2713e2232c25bfee68363b');
        $this->gy = $h('ca6734e1b59c0b0359814dcf6563da421da8bc3d81a93a3a7e73c355bd2864b5');
        // Tonelli–Shanks params (unused for MDC since p ≡ 3 mod 4)
        $this->tsNonQR = gmp_init(2);
        $this->tsT     = gmp_div($this->p, 2);
        $this->tsS     = 1;
    }

    public static function instance(): self {
        return self::$instance ??= new self();
    }
}

// -----------------------------------------------------------------------------
// Concrete curve: Curve25519 (p ≡ 1 mod 4, Tonelli–Shanks with S=2)
// -----------------------------------------------------------------------------

final class Curve25519Curve extends EdwardCurve {
    private static ?self $instance = null;

    private function __construct() {
        $h = fn(string $hex): GMP => gmp_init($hex, 16);
        $this->byteLength = 32;
        $this->p  = $h('7fffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffed');
        $this->q  = $h('1000000000000000000000000000000014def9dea2f79cd65812631a5cf5d3ed');
        $this->d  = $h('2dfc9311d490018c7338bf8688861767ff8ff5b2bebe27548a14b235eca6874a');
        $this->gx = $h('159a6849e44c3c7f061b3d570fc4ed5b5d14c8ba4253df49cc7edf80f533ad9b');
        $this->gy = $h('6666666666666666666666666666666666666666666666666666666666666658');
        // Tonelli–Shanks: nonQR=2, S=2, T = p>>2
        $this->tsNonQR = gmp_init(2);
        $this->tsT     = gmp_div($this->p, 4);
        $this->tsS     = 2;
    }

    public static function instance(): self {
        return self::$instance ??= new self();
    }
}
