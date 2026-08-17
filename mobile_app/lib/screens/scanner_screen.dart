import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';
import '../theme.dart';

class ScannerScreen extends StatefulWidget {
  final AppUser user;
  /// The item the user picked from the "Pending Scan" list before scanning.
  /// When set, only QR codes belonging to this item/group are accepted.
  final DeliveryItem expectedItem;

  const ScannerScreen({super.key, required this.user, required this.expectedItem});

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

/// Whether the camera is actively looking for a code, or paused while the
/// last result is shown and waiting for the user to say "scan the next one".
enum _Phase { scanning, result }

class _ScannerScreenState extends State<ScannerScreen> {
  final _api = ApiClient();
  final _controller = MobileScannerController(detectionSpeed: DetectionSpeed.noDuplicates);

  _Phase _phase = _Phase.scanning;
  bool _busy = false;
  bool _lastWasError = false;
  String? _resultTitle;
  String? _resultDetail;
  final List<String> _confirmedThisSession = [];
  late int _remaining = widget.expectedItem.unitCount;

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
      final result = await _api.confirmDelivery(
        widget.user,
        code,
        expectedGroupKey: widget.expectedItem.groupKey,
      );
      _confirmedThisSession.add('${result.itemName} (${result.requestNumber})');
      setState(() {
        _remaining = result.remainingInGroup;
        _lastWasError = false;
        _resultTitle = result.itemName;
        _resultDetail = result.message;
        _phase = _Phase.result;
        _busy = false;
      });
      if (result.isGroupComplete) return; // stays on the result panel; user taps "Done"
    } catch (e) {
      setState(() {
        _lastWasError = true;
        _resultTitle = 'Scan not accepted';
        _resultDetail = e.toString().replaceFirst('ApiException: ', '');
        _phase = _Phase.result;
        _busy = false;
      });
    }
  }

  Future<void> _scanNext() async {
    setState(() => _phase = _Phase.scanning);
    await _controller.start();
  }

  bool get _isGroupComplete => !_lastWasError && _remaining <= 0;

  void _finish() {
    final summary = _confirmedThisSession.isEmpty
        ? null
        : _confirmedThisSession.length == 1
            ? 'Delivery confirmed: ${_confirmedThisSession.first}.'
            : '${_confirmedThisSession.length} item(s) confirmed: ${_confirmedThisSession.join(', ')}.';
    Navigator.of(context).pop(summary);
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) _finish();
      },
      child: Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          foregroundColor: Colors.white,
          elevation: 0,
          title: Text(
            widget.expectedItem.unitCount > 1
                ? '${widget.expectedItem.itemLabel} · $_remaining left'
                : widget.expectedItem.itemLabel,
            overflow: TextOverflow.ellipsis,
          ),
          leading: IconButton(icon: const Icon(Icons.close), onPressed: _finish),
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
            _ScannerOverlay(active: _phase == _Phase.scanning && !_busy),
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
                        child: Text(
                          'Point the camera at "${widget.expectedItem.itemLabel}"\'s QR code',
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13),
                        ),
                      ),
                  ],
                ),
              ),
            if (_phase == _Phase.result)
              _ResultPanel(
                isError: _lastWasError,
                isComplete: _isGroupComplete,
                title: _resultTitle ?? '',
                detail: _resultDetail ?? '',
                remaining: _remaining,
                totalUnits: widget.expectedItem.unitCount,
                onScanNext: _scanNext,
                onDone: _finish,
              ),
          ],
        ),
      ),
    );
  }
}

/// Bottom sheet-style panel shown after every scan (success or failure),
/// replacing the old auto-advance-after-a-timer behavior — the user now
/// reviews what happened and explicitly chooses to continue.
class _ResultPanel extends StatelessWidget {
  final bool isError;
  final bool isComplete;
  final String title;
  final String detail;
  final int remaining;
  final int totalUnits;
  final VoidCallback onScanNext;
  final VoidCallback onDone;

  const _ResultPanel({
    required this.isError,
    required this.isComplete,
    required this.title,
    required this.detail,
    required this.remaining,
    required this.totalUnits,
    required this.onScanNext,
    required this.onDone,
  });

  @override
  Widget build(BuildContext context) {
    final accent = isError ? AppColors.danger : AppColors.success;
    final icon = isError ? Icons.error_outline : (isComplete ? Icons.task_alt : Icons.check_circle);

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
          padding: EdgeInsets.fromLTRB(22, 24, 22, MediaQuery.of(context).padding.bottom + 20),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.only(topLeft: Radius.circular(24), topRight: Radius.circular(24)),
            boxShadow: [BoxShadow(color: Color(0x40000000), blurRadius: 20)],
          ),
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
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 46, height: 46,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: accent.withValues(alpha: 0.12), shape: BoxShape.circle),
                    child: Icon(icon, color: accent, size: 24),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          isError ? 'Scan not accepted' : (isComplete ? 'All units confirmed' : 'Confirmed'),
                          style: TextStyle(color: accent, fontWeight: FontWeight.w800, fontSize: 12.5, letterSpacing: 0.2),
                        ),
                        const SizedBox(height: 2),
                        Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: AppColors.ink)),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(detail, style: TextStyle(fontSize: 13.5, color: Colors.black.withValues(alpha: 0.6), height: 1.4)),
              if (!isError && !isComplete) ...[
                const SizedBox(height: 14),
                ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: LinearProgressIndicator(
                    value: (totalUnits - remaining) / totalUnits,
                    minHeight: 6,
                    backgroundColor: AppColors.border,
                    valueColor: const AlwaysStoppedAnimation(AppColors.success),
                  ),
                ),
                const SizedBox(height: 6),
                Text('${totalUnits - remaining} of $totalUnits units confirmed', style: const TextStyle(fontSize: 11.5, color: Colors.black45)),
              ],
              const SizedBox(height: 20),
              SizedBox(
                height: 50,
                child: ElevatedButton.icon(
                  onPressed: isComplete ? onDone : onScanNext,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: isError ? AppColors.primary : (isComplete ? AppColors.success : AppColors.primary),
                  ),
                  icon: Icon(isComplete ? Icons.check : (isError ? Icons.refresh : Icons.qr_code_scanner)),
                  label: Text(isComplete ? 'Done' : (isError ? 'Try Again' : 'Scan Next QR Code')),
                ),
              ),
              if (!isComplete) ...[
                const SizedBox(height: 8),
                SizedBox(
                  height: 42,
                  child: TextButton(
                    onPressed: onDone,
                    child: const Text('Finish later', style: TextStyle(color: Colors.black45, fontWeight: FontWeight.w600)),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Viewfinder frame with corner brackets (instead of a plain square border)
/// and a subtle scan-line sweep while actively looking for a code.
class _ScannerOverlay extends StatefulWidget {
  final bool active;
  const _ScannerOverlay({required this.active});

  @override
  State<_ScannerOverlay> createState() => _ScannerOverlayState();
}

class _ScannerOverlayState extends State<_ScannerOverlay> with SingleTickerProviderStateMixin {
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
                          boxShadow: [BoxShadow(color: AppColors.success.withValues(alpha: 0.7), blurRadius: 6)],
                          gradient: LinearGradient(
                            colors: [Colors.transparent, AppColors.success, Colors.transparent],
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

    // Top-left
    canvas.drawPath(
      Path()
        ..moveTo(0, len)
        ..lineTo(0, r)
        ..arcToPoint(Offset(r, 0), radius: const Radius.circular(r))
        ..lineTo(len, 0),
      paint,
    );
    // Top-right
    canvas.drawPath(
      Path()
        ..moveTo(size.width - len, 0)
        ..lineTo(size.width - r, 0)
        ..arcToPoint(Offset(size.width, r), radius: const Radius.circular(r))
        ..lineTo(size.width, len),
      paint,
    );
    // Bottom-left
    canvas.drawPath(
      Path()
        ..moveTo(0, size.height - len)
        ..lineTo(0, size.height - r)
        ..arcToPoint(Offset(r, size.height), radius: const Radius.circular(r), clockwise: false)
        ..lineTo(len, size.height),
      paint,
    );
    // Bottom-right
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
