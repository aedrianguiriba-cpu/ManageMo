import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';
import '../theme.dart';

/// Read-only "check item details" scanner — separate from [ScannerScreen],
/// which confirms a delivery (a write). This one never writes anything: scan
/// any of your own items' QR codes (owned, currently borrowed, or under an
/// active request) and see its full details. Available from the bottom nav
/// at any time, not tied to a specific pending delivery.
class ItemLookupScannerScreen extends StatefulWidget {
  final AppUser user;
  const ItemLookupScannerScreen({super.key, required this.user});

  @override
  State<ItemLookupScannerScreen> createState() => _ItemLookupScannerScreenState();
}

enum _Phase { scanning, result }

class _ItemLookupScannerScreenState extends State<ItemLookupScannerScreen> {
  final _api = ApiClient();
  final _controller = MobileScannerController(detectionSpeed: DetectionSpeed.noDuplicates);

  _Phase _phase = _Phase.scanning;
  bool _busy = false;
  bool _lastWasError = false;
  ItemLookupResult? _result;
  String? _errorDetail;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_busy || _phase != _Phase.scanning) return;
    final code = capture.barcodes.firstOrNull?.rawValue;
    if (code == null || code.isEmpty) return;

    setState(() => _busy = true);
    await _controller.stop();

    try {
      final result = await _api.lookupItemDetails(widget.user, code);
      setState(() {
        _result = result;
        _lastWasError = false;
        _phase = _Phase.result;
        _busy = false;
      });
    } catch (e) {
      setState(() {
        _lastWasError = true;
        _errorDetail = e.toString().replaceFirst('ApiException: ', '');
        _result = null;
        _phase = _Phase.result;
        _busy = false;
      });
    }
  }

  Future<void> _scanAnother() async {
    setState(() {
      _phase = _Phase.scanning;
      _result = null;
      _errorDetail = null;
    });
    await _controller.start();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text('Check Item Details'),
        actions: [
          IconButton(
            icon: ValueListenableBuilder(
              valueListenable: _controller,
              builder: (context, state, child) {
                return Icon(state.torchState == TorchState.on ? Icons.flash_on : Icons.flash_off);
              },
            ),
            onPressed: () => _controller.toggleTorch(),
          ),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),
          _LookupOverlay(active: _phase == _Phase.scanning && !_busy),
          if (_phase == _Phase.scanning)
            Positioned(
              left: 0, right: 0, bottom: 48,
              child: Column(
                children: [
                  if (_busy)
                    const CircularProgressIndicator(color: Colors.white)
                  else
                    Container(
                      margin: const EdgeInsets.symmetric(horizontal: 32),
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.55),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Text(
                        "Point the camera at any of your items' QR code to check its details",
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13),
                      ),
                    ),
                ],
              ),
            ),
          if (_phase == _Phase.result)
            _LookupResultPanel(
              isError: _lastWasError,
              result: _result,
              errorDetail: _errorDetail,
              onScanAnother: _scanAnother,
              onDone: () => Navigator.of(context).pop(),
            ),
        ],
      ),
    );
  }
}

class _LookupResultPanel extends StatelessWidget {
  final bool isError;
  final ItemLookupResult? result;
  final String? errorDetail;
  final VoidCallback onScanAnother;
  final VoidCallback onDone;

  const _LookupResultPanel({
    required this.isError,
    required this.result,
    required this.errorDetail,
    required this.onScanAnother,
    required this.onDone,
  });

  Color _statusColor(String key) {
    switch (key) {
      case 'owned':
        return AppColors.info;
      case 'overdue':
        return AppColors.danger;
      case 'pending':
        return AppColors.warning;
      case 'borrowed':
      default:
        return AppColors.primary;
    }
  }

  @override
  Widget build(BuildContext context) {
    final accent = isError ? AppColors.danger : (result != null ? _statusColor(result!.statusColorKey) : AppColors.primary);

    return Positioned(
      left: 0, right: 0, bottom: 0,
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 1.0, end: 0.0),
        duration: const Duration(milliseconds: 240),
        curve: Curves.easeOut,
        builder: (context, value, child) => FractionalTranslation(
          translation: Offset(0, value),
          child: child,
        ),
        child: Container(
          constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.72),
          padding: EdgeInsets.fromLTRB(22, 24, 22, MediaQuery.of(context).padding.bottom + 20),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.only(topLeft: Radius.circular(24), topRight: Radius.circular(24)),
            boxShadow: [BoxShadow(color: Color(0x40000000), blurRadius: 20)],
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 40, height: 4,
                    margin: const EdgeInsets.only(bottom: 18),
                    decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
                  ),
                ),
                if (isError) ...[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 46, height: 46,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(color: accent.withValues(alpha: 0.12), shape: BoxShape.circle),
                        child: Icon(Icons.error_outline, color: accent, size: 24),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Not found', style: TextStyle(color: accent, fontWeight: FontWeight.w800, fontSize: 12.5, letterSpacing: 0.2)),
                            const SizedBox(height: 2),
                            Text(errorDetail ?? 'That QR code could not be looked up.',
                                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: AppColors.ink)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ] else if (result != null) ...[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 46, height: 46,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(color: accent.withValues(alpha: 0.12), shape: BoxShape.circle),
                        child: Icon(Icons.inventory_2_outlined, color: accent, size: 22),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Text(result!.statusLabel.toUpperCase(),
                                    style: TextStyle(color: accent, fontWeight: FontWeight.w800, fontSize: 12, letterSpacing: 0.3)),
                                const Text(' · ', style: TextStyle(color: Colors.black26)),
                                Expanded(
                                  child: Text(result!.ownershipLabel,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(color: Colors.black45, fontWeight: FontWeight.w600, fontSize: 12)),
                                ),
                              ],
                            ),
                            const SizedBox(height: 2),
                            Text(result!.itemName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: AppColors.ink)),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  _DetailGrid(result: result!),
                ],
                const SizedBox(height: 20),
                SizedBox(
                  height: 50,
                  child: ElevatedButton.icon(
                    onPressed: onScanAnother,
                    style: ElevatedButton.styleFrom(backgroundColor: isError ? AppColors.primary : accent),
                    icon: Icon(isError ? Icons.refresh : Icons.qr_code_scanner),
                    label: Text(isError ? 'Try Again' : 'Scan Another Item'),
                  ),
                ),
                const SizedBox(height: 8),
                SizedBox(
                  height: 42,
                  child: TextButton(
                    onPressed: onDone,
                    child: const Text('Done', style: TextStyle(color: Colors.black45, fontWeight: FontWeight.w600)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DetailGrid extends StatelessWidget {
  final ItemLookupResult result;
  const _DetailGrid({required this.result});

  @override
  Widget build(BuildContext context) {
    final rows = <MapEntry<String, String>>[
      if (result.category != null && result.category!.isNotEmpty) MapEntry('Category', result.category!),
      if (result.condition != null && result.condition!.isNotEmpty)
        MapEntry('Condition', result.condition![0].toUpperCase() + result.condition!.substring(1)),
      ...result.details.entries,
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 18,
          runSpacing: 10,
          children: rows.map((e) => _DetailField(label: e.key, value: e.value)).toList(),
        ),
        if (result.description != null && result.description!.isNotEmpty) ...[
          const SizedBox(height: 14),
          Text('DESCRIPTION', style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: Colors.black.withValues(alpha: 0.4), letterSpacing: 0.4)),
          const SizedBox(height: 4),
          Text(result.description!, style: TextStyle(fontSize: 13.5, color: Colors.black.withValues(alpha: 0.65), height: 1.4)),
        ],
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(8)),
          child: Row(
            children: [
              const Icon(Icons.qr_code, size: 14, color: Colors.black38),
              const SizedBox(width: 6),
              Expanded(
                child: Text(result.qrCodeId, style: const TextStyle(fontSize: 11.5, fontFamily: 'monospace', color: Colors.black54)),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _DetailField extends StatelessWidget {
  final String label;
  final String value;
  const _DetailField({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 150,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(), style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: Colors.black.withValues(alpha: 0.4), letterSpacing: 0.4)),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: AppColors.ink)),
        ],
      ),
    );
  }
}

/// Viewfinder frame — same corner-bracket look as the delivery scanner's
/// overlay, kept as its own small copy here so this screen stays self-contained.
class _LookupOverlay extends StatefulWidget {
  final bool active;
  const _LookupOverlay({required this.active});

  @override
  State<_LookupOverlay> createState() => _LookupOverlayState();
}

class _LookupOverlayState extends State<_LookupOverlay> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    const frameSize = 260.0;
    return IgnorePointer(
      child: Center(
        child: SizedBox(
          width: frameSize,
          height: frameSize,
          child: Stack(
            children: [
              CustomPaint(size: const Size(frameSize, frameSize), painter: _CornerBracketsPainter()),
              if (widget.active)
                AnimatedBuilder(
                  animation: _controller,
                  builder: (context, child) {
                    return Positioned(
                      top: 12 + _controller.value * (frameSize - 24),
                      left: 12,
                      right: 12,
                      child: Container(
                        height: 2.5,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(2),
                          boxShadow: [BoxShadow(color: AppColors.info.withValues(alpha: 0.7), blurRadius: 6)],
                          gradient: LinearGradient(
                            colors: [Colors.transparent, AppColors.info, Colors.transparent],
                          ),
                        ),
                      ),
                    );
                  },
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CornerBracketsPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withValues(alpha: 0.9)
      ..strokeWidth = 3.5
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;
    const len = 26.0;
    const r = 16.0;

    canvas.drawPath(
      Path()
        ..moveTo(0, len)
        ..lineTo(0, r)
        ..arcToPoint(Offset(r, 0), radius: const Radius.circular(r))
        ..lineTo(len, 0),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(size.width - len, 0)
        ..lineTo(size.width - r, 0)
        ..arcToPoint(Offset(size.width, r), radius: const Radius.circular(r))
        ..lineTo(size.width, len),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(0, size.height - len)
        ..lineTo(0, size.height - r)
        ..arcToPoint(Offset(r, size.height), radius: const Radius.circular(r), clockwise: false)
        ..lineTo(len, size.height),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(size.width, size.height - len)
        ..lineTo(size.width, size.height - r)
        ..arcToPoint(Offset(size.width - r, size.height), radius: const Radius.circular(r))
        ..lineTo(size.width - len, size.height),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

extension _FirstOrNull<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
