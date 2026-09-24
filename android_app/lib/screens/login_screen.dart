import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme.dart';
import 'home_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with TickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _api = ApiClient();

  bool _loading = false;
  String? _error;
  bool _obscurePassword = true;

  // One-shot entrance: header, headline, and the form sheet each fade/slide in
  // on their own stagger rather than all popping in at once.
  late final AnimationController _entrance;
  late final Animation<double> _headerFade;
  late final Animation<Offset> _headerSlide;
  late final Animation<double> _headlineFade;
  late final Animation<Offset> _headlineSlide;
  late final Animation<double> _sheetFade;
  late final Animation<Offset> _sheetSlide;

  // Slow, endless drift for the decorative bubbles so the header doesn't feel
  // static — small vertical bob, not a distraction.
  late final AnimationController _bubbles;

  @override
  void initState() {
    super.initState();

    _entrance = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));

    _headerFade = CurvedAnimation(parent: _entrance, curve: const Interval(0.0, 0.45, curve: Curves.easeOut));
    _headerSlide = Tween(begin: const Offset(0, -0.25), end: Offset.zero)
        .animate(CurvedAnimation(parent: _entrance, curve: const Interval(0.0, 0.45, curve: Curves.easeOut)));

    _headlineFade = CurvedAnimation(parent: _entrance, curve: const Interval(0.15, 0.6, curve: Curves.easeOut));
    _headlineSlide = Tween(begin: const Offset(0, 0.2), end: Offset.zero)
        .animate(CurvedAnimation(parent: _entrance, curve: const Interval(0.15, 0.6, curve: Curves.easeOut)));

    _sheetFade = CurvedAnimation(parent: _entrance, curve: const Interval(0.35, 1.0, curve: Curves.easeOut));
    _sheetSlide = Tween(begin: const Offset(0, 0.12), end: Offset.zero)
        .animate(CurvedAnimation(parent: _entrance, curve: const Interval(0.35, 1.0, curve: Curves.easeOutCubic)));

    _entrance.forward();

    _bubbles = AnimationController(vsync: this, duration: const Duration(seconds: 5))..repeat(reverse: true);
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _entrance.dispose();
    _bubbles.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final user = await _api.login(_emailController.text.trim(), _passwordController.text);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => HomeScreen(user: user)),
      );
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('ApiException: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primary,
      body: Stack(
        children: [
          // Decorative translucent bubbles floating on the maroon field, same
          // idea as the reference mock's blue blob background — each drifts
          // gently up/down on its own offset from the shared bubble clock.
          _FloatingBubble(controller: _bubbles, phase: 0.0, drift: 10, top: -60, right: -40, size: 190, opacity: 0.10),
          _FloatingBubble(controller: _bubbles, phase: 0.5, drift: 14, top: 40, right: 60, size: 70, opacity: 0.14),
          _FloatingBubble(controller: _bubbles, phase: 0.25, drift: 8, top: 120, left: -50, size: 140, opacity: 0.08),
          SafeArea(
            bottom: false,
            child: Column(
              children: [
                // Brand header sits directly on the maroon field.
                FadeTransition(
                  opacity: _headerFade,
                  child: SlideTransition(
                    position: _headerSlide,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(24, 20, 24, 22),
                      child: Row(
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Container(
                              padding: const EdgeInsets.all(6),
                              color: Colors.white,
                              child: Image.asset('assets/images/logo.png', width: 30, height: 30),
                            ),
                          ),
                          const SizedBox(width: 10),
                          const Text(
                            'ManageMo',
                            style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: -0.3),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                // Headline area, still on the maroon field, like "Welcome Back"
                // sitting above the wave in the reference screens.
                FadeTransition(
                  opacity: _headlineFade,
                  child: SlideTransition(
                    position: _headlineSlide,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(28, 8, 28, 30),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Welcome\nBack',
                            style: TextStyle(fontSize: 34, fontWeight: FontWeight.w900, color: Colors.white, height: 1.12),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Log in to scan and confirm your deliveries.',
                            style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500, color: Colors.white.withValues(alpha: 0.85)),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                // White wave sheet with the form.
                Expanded(
                  child: FadeTransition(
                    opacity: _sheetFade,
                    child: SlideTransition(
                      position: _sheetSlide,
                      child: ClipPath(
                        clipper: _WaveClipper(),
                        child: Container(
                          width: double.infinity,
                          color: AppColors.surface,
                          padding: const EdgeInsets.fromLTRB(28, 40, 28, 24),
                          child: SingleChildScrollView(
                            child: Form(
                              key: _formKey,
                              child: ConstrainedBox(
                                constraints: const BoxConstraints(maxWidth: 420),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.stretch,
                                  children: [
                                    TextFormField(
                                      controller: _emailController,
                                      decoration: const InputDecoration(
                                        labelText: 'Email',
                                        prefixIcon: Icon(Icons.email_outlined),
                                      ),
                                      keyboardType: TextInputType.emailAddress,
                                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Email is required' : null,
                                    ),
                                    const SizedBox(height: 14),
                                    TextFormField(
                                      controller: _passwordController,
                                      obscureText: _obscurePassword,
                                      decoration: InputDecoration(
                                        labelText: 'Password',
                                        prefixIcon: const Icon(Icons.lock_outline),
                                        suffixIcon: IconButton(
                                          icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility),
                                          onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                        ),
                                      ),
                                      validator: (v) => (v == null || v.isEmpty) ? 'Password is required' : null,
                                      onFieldSubmitted: (_) => _submit(),
                                    ),
                                    AnimatedSize(
                                      duration: const Duration(milliseconds: 250),
                                      curve: Curves.easeOut,
                                      alignment: Alignment.topCenter,
                                      child: _error == null
                                          ? const SizedBox(width: double.infinity)
                                          : Padding(
                                              padding: const EdgeInsets.only(top: 14),
                                              child: Container(
                                                padding: const EdgeInsets.all(12),
                                                decoration: BoxDecoration(
                                                  color: AppColors.dangerSoft,
                                                  borderRadius: BorderRadius.circular(10),
                                                  border: Border.all(color: AppColors.danger.withValues(alpha: 0.25)),
                                                ),
                                                child: Row(
                                                  children: [
                                                    const Icon(Icons.error_outline, color: AppColors.danger, size: 18),
                                                    const SizedBox(width: 8),
                                                    Expanded(child: Text(_error ?? '', style: const TextStyle(color: AppColors.danger, fontSize: 13))),
                                                  ],
                                                ),
                                              ),
                                            ),
                                    ),
                                    const SizedBox(height: 26),
                                    SizedBox(
                                      height: 52,
                                      child: ElevatedButton(
                                        onPressed: _loading ? null : _submit,
                                        style: ElevatedButton.styleFrom(
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
                                        ),
                                        child: AnimatedSwitcher(
                                          duration: const Duration(milliseconds: 200),
                                          child: _loading
                                              ? const SizedBox(
                                                  key: ValueKey('spinner'),
                                                  width: 22, height: 22,
                                                  child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
                                              : const Text('Log In', key: ValueKey('label')),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(height: 22),
                                    // No self-serve sign-up in this app — accounts are
                                    // provisioned by an admin (property custodian
                                    // logins) — so this fills that slot honestly
                                    // instead of a dead "Sign up" button.
                                    Center(
                                      child: Text.rich(
                                        TextSpan(
                                          style: TextStyle(fontSize: 13, color: Colors.black.withValues(alpha: 0.45)),
                                          children: const [
                                            TextSpan(text: "Don't have an account? "),
                                            TextSpan(
                                              text: 'Ask your administrator',
                                              style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.primary),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                    // Fills what used to be a big empty gap below the
                                    // form with a quick feature strip + a grounding
                                    // footer line, faded/slid in after everything else.
                                    const SizedBox(height: 36),
                                    FadeTransition(
                                      opacity: CurvedAnimation(parent: _entrance, curve: const Interval(0.6, 1.0, curve: Curves.easeOut)),
                                      child: const _LoginFooter(),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Fills the space under the form with a looping "packing a box and loading
/// it onto the truck" animation + a grounding footer line, instead of
/// leaving a big blank gap beneath "Ask your administrator".
class _LoginFooter extends StatelessWidget {
  const _LoginFooter();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const _PackingTruckAnimation(),
        const SizedBox(height: 20),
        const Divider(color: AppColors.border, height: 1),
        const SizedBox(height: 14),
        Text(
          'Pampanga State University · Property Management',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: Colors.black.withValues(alpha: 0.35)),
        ),
        const SizedBox(height: 8),
      ],
    );
  }
}

/// A small looping delivery scene: a box slides in, gets "packed" (a quick
/// squash-seal), rides along the ground to the truck, lifts into the cargo
/// bed and fades in, then the truck gives a little "loaded" bounce before
/// the whole cycle repeats with the next box — self-contained (no external
/// animation assets) since this app doesn't ship Lottie/Rive files.
class _PackingTruckAnimation extends StatefulWidget {
  const _PackingTruckAnimation();

  @override
  State<_PackingTruckAnimation> createState() => _PackingTruckAnimationState();
}

class _PackingTruckAnimationState extends State<_PackingTruckAnimation> with SingleTickerProviderStateMixin {
  late final AnimationController _c;

  // Cycle timeline (fractions of the loop):
  // 0.00–0.08  box drops in and "seals" (pack)
  // 0.08–0.26  box rides along the ground toward the truck
  // 0.26–0.36  box lifts and slides into the cargo bed, fading in (loaded)
  // 0.36–0.40  truck gives a small "got it" bounce
  // 0.40–0.62  truck drives (visibly, across the screen) to the delivery spot
  //            on the right, with the box hidden inside
  // 0.62–0.66  arrival — a small stop bounce
  // 0.66–0.82  the box comes back out of the cargo bed and settles onto the
  //            ground at the delivery spot (discharging it there)
  // 0.82–0.88  delivered box sits for a beat, small confirm settle
  // 0.88–1.00  truck drives off-screen to its next stop, box left behind
  //            fades with it, then the loop restarts with a new box
  static const _packEnd = 0.08;
  static const _rideEnd = 0.26;
  static const _loadEnd = 0.36;
  static const _bounceEnd = 0.40;
  static const _arriveEnd = 0.62;
  static const _stopEnd = 0.66;
  static const _dischargeEnd = 0.82;
  static const _settleEnd = 0.88;

  @override
  void initState() {
    super.initState();
    _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 4800))..repeat();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 72,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final width = constraints.maxWidth;
          const truckWidth = 58.0;
          const boxSize = 22.0;
          // Both the truck and the packing station sit on the left now — the
          // truck parks left-of-center (not jammed at the very edge) so
          // there's room for a packing spot further left, and the box rides
          // rightward that short stretch into the cargo bed. That leaves the
          // rest of the width open as the road for the delivery drive-off.
          final truckLeft = width * 0.28;
          final loadCargoX = truckLeft + truckWidth * 0.42;
          final rideStartX = 4.0;
          final rideEndX = loadCargoX - 10;
          // Delivery stop, near the right edge — where the truck actually
          // drops the item, after visibly driving there from the load spot.
          final destX = width - truckWidth - 10;
          final destCargoX = destX + truckWidth * 0.42;

          return AnimatedBuilder(
            animation: _c,
            builder: (context, child) {
              final t = _c.value;

              double boxX;
              double boxY; // 0 = ground level, negative = lifted/inside truck
              double boxOpacity;
              double boxScaleY = 1;

              if (t < _packEnd) {
                // Drop-in + seal squash.
                final p = t / _packEnd;
                boxX = rideStartX;
                boxY = 0;
                boxOpacity = p; // fades in as it "appears" on the belt
                boxScaleY = 1 - (0.18 * math.sin(p * math.pi)); // quick squash as it "seals"
              } else if (t < _rideEnd) {
                final p = (t - _packEnd) / (_rideEnd - _packEnd);
                boxX = rideStartX + (rideEndX - rideStartX) * Curves.easeInOut.transform(p);
                boxY = -2 * math.sin(p * math.pi); // gentle bob while riding the belt
                boxOpacity = 1;
              } else if (t < _loadEnd) {
                final p = (t - _rideEnd) / (_loadEnd - _rideEnd);
                boxX = rideEndX + (loadCargoX - rideEndX) * Curves.easeIn.transform(p);
                boxY = -10 - 14 * p; // lifts up into the cargo bed
                boxOpacity = 1 - p; // disappears as it settles inside
              } else if (t < _dischargeEnd) {
                // Hidden inside the cargo bed while the truck visibly drives
                // over to the delivery spot, then reappears there and is set
                // down on the ground — this is the discharge/unloading itself.
                if (t < _stopEnd) {
                  boxX = destCargoX;
                  boxY = -24;
                  boxOpacity = 0;
                } else {
                  final p = (t - _stopEnd) / (_dischargeEnd - _stopEnd);
                  final eased = Curves.easeOut.transform(p);
                  boxX = destCargoX;
                  boxY = -24 * (1 - eased); // descends out of the cargo bed to the ground
                  boxOpacity = p < 0.15 ? p / 0.15 : 1; // fades in as it emerges
                  if (p > 0.85) {
                    // Small landing squash right as it touches down.
                    boxScaleY = 1 - (0.15 * math.sin(((p - 0.85) / 0.15) * math.pi));
                  }
                }
              } else if (t < _settleEnd) {
                // Delivered — sits on the ground for a confirming beat.
                final p = (t - _dischargeEnd) / (_settleEnd - _dischargeEnd);
                boxX = destCargoX;
                boxY = 0;
                boxOpacity = 1;
                boxScaleY = 1 + (0.06 * math.sin(p * math.pi)); // gentle "delivered" pulse
              } else {
                // Fades out together with the truck as it drives off to its
                // next stop, left behind at the delivery spot, so the loop
                // resets cleanly.
                final p = (t - _settleEnd) / (1 - _settleEnd);
                boxX = destCargoX;
                boxY = 0;
                boxOpacity = p < 0.5 ? 1 : (1 - (p - 0.5) / 0.5);
              }

              // Truck: small "got it" bounce right after loading, then
              // visibly drives across to the delivery spot, stops with a
              // small arrival bounce, sets the box down, gives a "delivered"
              // settle bounce, then drives off-screen to its next stop.
              double truckX = truckLeft;
              double truckScaleY = 1;
              double truckOpacity = 1;
              double speedLines = 0;
              if (t < _bounceEnd) {
                if (t >= _loadEnd) {
                  final p = (t - _loadEnd) / (_bounceEnd - _loadEnd);
                  truckScaleY = 1 - (0.06 * math.sin(p * math.pi));
                }
              } else if (t < _arriveEnd) {
                final p = Curves.easeInOut.transform((t - _bounceEnd) / (_arriveEnd - _bounceEnd));
                truckX = truckLeft + (destX - truckLeft) * p;
                truckScaleY = 1 - (0.025 * (math.sin(p * 22).abs())); // small road jostle
                speedLines = 1.0;
              } else if (t < _stopEnd) {
                truckX = destX;
                final p = (t - _arriveEnd) / (_stopEnd - _arriveEnd);
                truckScaleY = 1 - (0.05 * math.sin(p * math.pi)); // arrival bounce
              } else if (t < _settleEnd) {
                truckX = destX;
                if (t >= _dischargeEnd) {
                  final p = (t - _dischargeEnd) / (_settleEnd - _dischargeEnd);
                  truckScaleY = 1 - (0.04 * math.sin(p * math.pi)); // eases as it "sets down" the load
                }
              } else {
                final p = Curves.easeIn.transform((t - _settleEnd) / (1 - _settleEnd));
                truckX = destX + (width - destX + truckWidth) * p;
                truckScaleY = 1 - (0.03 * (math.sin(p * 26).abs())); // small road jostle
                // Stays fully visible while driving away, then fades out over
                // the final stretch as it exits the screen.
                truckOpacity = p < 0.7 ? 1.0 : (1 - (p - 0.7) / 0.3);
                speedLines = p < 0.7 ? (p / 0.7) : 1.0;
              }

              return Stack(
                clipBehavior: Clip.none,
                children: [
                  // Ground / conveyor line.
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 14,
                    child: Container(height: 2, color: AppColors.border),
                  ),
                  // Speed lines trailing the truck once it's driving off.
                  if (speedLines > 0)
                    Positioned(
                      left: truckX - 26,
                      bottom: 18,
                      child: Opacity(
                        opacity: speedLines * 0.5,
                        child: Row(
                          children: List.generate(3, (i) => Container(
                                margin: const EdgeInsets.only(right: 4),
                                width: 10 - (i * 2),
                                height: 2,
                                color: AppColors.inkMuted,
                              )),
                        ),
                      ),
                    ),
                  // Truck — parked while loading, then drives off to deliver.
                  Positioned(
                    left: truckX,
                    bottom: 8,
                    child: Opacity(
                      opacity: truckOpacity,
                      child: Transform.scale(
                        alignment: Alignment.bottomCenter,
                        scaleY: truckScaleY,
                        child: const _TruckGlyph(width: truckWidth),
                      ),
                    ),
                  ),
                  // The box being packed and carried to the truck.
                  if (boxOpacity > 0)
                    Positioned(
                      left: boxX,
                      bottom: 14 + -boxY,
                      child: Opacity(
                        opacity: boxOpacity,
                        child: Transform.scale(
                          scaleY: boxScaleY,
                          alignment: Alignment.bottomCenter,
                          child: Icon(Icons.inventory_2_rounded, size: boxSize, color: AppColors.primary),
                        ),
                      ),
                    ),
                ],
              );
            },
          );
        },
      ),
    );
  }
}

/// A simple drawn delivery truck (cab + cargo box + two wheels) used by
/// [_PackingTruckAnimation] — plain Material icons don't have a "cargo bed
/// opening" a box can visually slide into, so this is drawn instead.
class _TruckGlyph extends StatelessWidget {
  final double width;

  const _TruckGlyph({required this.width});

  @override
  Widget build(BuildContext context) {
    final height = width * 0.62;
    return SizedBox(
      width: width,
      height: height + 8, // leaves room for the wheels to peek below the body
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          // Cargo box.
          Positioned(
            left: 0,
            top: height * 0.12,
            child: Container(
              width: width * 0.62,
              height: height * 0.7,
              decoration: BoxDecoration(
                color: AppColors.primary,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
          // Cab.
          Positioned(
            left: width * 0.60,
            top: height * 0.32,
            child: Container(
              width: width * 0.40,
              height: height * 0.5,
              decoration: const BoxDecoration(
                color: AppColors.primaryDark,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(3),
                  topRight: Radius.circular(8),
                  bottomRight: Radius.circular(3),
                ),
              ),
              child: Align(
                alignment: Alignment.topRight,
                child: Container(
                  margin: const EdgeInsets.only(top: 4, right: 4),
                  width: width * 0.16,
                  height: height * 0.18,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.85),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
            ),
          ),
          // Wheels.
          Positioned(left: width * 0.10, bottom: 0, child: _wheel()),
          Positioned(left: width * 0.72, bottom: 0, child: _wheel()),
        ],
      ),
    );
  }

  Widget _wheel() {
    return Container(
      width: 12,
      height: 12,
      decoration: BoxDecoration(
        color: AppColors.ink,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 2),
      ),
    );
  }
}

/// A soft translucent circle that drifts up/down endlessly, driven by a
/// shared [controller] so all bubbles stay in sync while each uses its own
/// [phase] offset so they don't move in lockstep.
class _FloatingBubble extends StatelessWidget {
  final AnimationController controller;
  final double phase;
  final double drift;
  final double size;
  final double opacity;
  final double? top;
  final double? left;
  final double? right;

  const _FloatingBubble({
    required this.controller,
    required this.phase,
    required this.drift,
    required this.size,
    required this.opacity,
    this.top,
    this.left,
    this.right,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final t = ((controller.value + phase) % 1.0) * 2 * math.pi;
        return Positioned(
          top: top == null ? null : top! + (drift * (0.5 - 0.5 * math.cos(t))),
          left: left,
          right: right,
          child: child!,
        );
      },
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: Colors.white.withValues(alpha: opacity),
        ),
      ),
    );
  }
}

/// Clips a gentle wave along the top edge of the white sheet, echoing the
/// curved handoff between the colored header and the form area in the
/// reference screens (rather than a plain straight/rounded-corner cut).
class _WaveClipper extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    final path = Path()..lineTo(0, 28);
    path.quadraticBezierTo(size.width * 0.25, 0, size.width * 0.5, 14);
    path.quadraticBezierTo(size.width * 0.75, 28, size.width, 0);
    path.lineTo(size.width, size.height);
    path.lineTo(0, size.height);
    path.close();
    return path;
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}
