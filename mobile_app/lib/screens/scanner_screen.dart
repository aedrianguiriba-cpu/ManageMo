import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';

class ScannerScreen extends StatefulWidget {
  final AppUser user;
  /// The item the user picked from the "Pending Scan" list before scanning.
  /// When set, only QR codes belonging to this item/group are accepted.
  final DeliveryItem expectedItem;

  const ScannerScreen({super.key, required this.user, required this.expectedItem});

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  final _api = ApiClient();
  final _controller = MobileScannerController(detectionSpeed: DetectionSpeed.noDuplicates);

  bool _busy = false;
  String? _error;
  String? _lastSuccessMessage;
  final List<String> _confirmedThisSession = [];
  late int _remaining = widget.expectedItem.unitCount;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_busy) return;
    final code = capture.barcodes.firstOrNull?.rawValue;
    if (code == null || code.isEmpty) return;

    setState(() {
      _busy = true;
      _error = null;
    });
    await _controller.stop();

    try {
      final result = await _api.confirmDelivery(
        widget.user,
        code,
        expectedGroupKey: widget.expectedItem.groupKey,
      );
      setState(() {
        _lastSuccessMessage = result.message;
        _remaining = result.remainingInGroup;
        _confirmedThisSession.add('${result.itemName} (${result.requestNumber})');
      });

      if (result.isGroupComplete) {
        await Future.delayed(const Duration(milliseconds: 1100));
        if (mounted) _finish();
        return;
      }
      // More units left in this item — keep scanning.
      await Future.delayed(const Duration(milliseconds: 900));
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('ApiException: ', ''));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
        await _controller.start();
      }
    }
  }

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
            TextButton(
              onPressed: _finish,
              child: const Text('Done', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
            ),
          ],
        ),
        body: Stack(
          fit: StackFit.expand,
          children: [
            MobileScanner(controller: _controller, onDetect: _onDetect),
            const _ScannerOverlay(),
            Positioned(
              left: 0, right: 0, bottom: 40,
              child: Column(
                children: [
                  if (_busy)
                    const CircularProgressIndicator(color: Colors.white)
                  else
                    Text(
                      'Scan the QR code on "${widget.expectedItem.itemLabel}"',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
                    ),
                  if (_lastSuccessMessage != null && _error == null) ...[
                    const SizedBox(height: 12),
                    Container(
                      margin: const EdgeInsets.symmetric(horizontal: 24),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFF15803D).withValues(alpha: 0.92),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(_lastSuccessMessage!,
                          textAlign: TextAlign.center, style: const TextStyle(color: Colors.white)),
                    ),
                  ],
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      margin: const EdgeInsets.symmetric(horizontal: 24),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.red.withValues(alpha: 0.85),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white)),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ScannerOverlay extends StatelessWidget {
  const _ScannerOverlay();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 260,
        height: 260,
        decoration: BoxDecoration(
          border: Border.all(color: Colors.white.withValues(alpha: 0.85), width: 2.5),
          borderRadius: BorderRadius.circular(16),
        ),
      ),
    );
  }
}

extension _FirstOrNull<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
