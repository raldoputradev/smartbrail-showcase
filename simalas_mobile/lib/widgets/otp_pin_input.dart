import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Input OTP 6 digit — satu kotak per angka (gaya PIN).
class OtpPinInput extends StatefulWidget {
  const OtpPinInput({
    super.key,
    required this.controller,
    this.length = 6,
    this.enabled = true,
  });

  final TextEditingController controller;
  final int length;
  final bool enabled;

  @override
  State<OtpPinInput> createState() => _OtpPinInputState();
}

class _OtpPinInputState extends State<OtpPinInput> {
  late final List<FocusNode> _nodes;
  late final List<TextEditingController> _cells;

  @override
  void initState() {
    super.initState();
    _nodes = List.generate(widget.length, (_) => FocusNode());
    _cells = List.generate(widget.length, (_) => TextEditingController());
    _syncFromParent(widget.controller.text);
    widget.controller.addListener(_syncFromParentListener);
  }

  @override
  void didUpdateWidget(covariant OtpPinInput oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_syncFromParentListener);
      widget.controller.addListener(_syncFromParentListener);
    }
    if (oldWidget.length != widget.length) {
      return;
    }
    _syncFromParent(widget.controller.text);
  }

  void _syncFromParentListener() => _syncFromParent(widget.controller.text);

  void _syncFromParent(String value) {
    final digits = value.replaceAll(RegExp(r'\D'), '');
    for (var i = 0; i < widget.length; i++) {
      final ch = i < digits.length ? digits[i] : '';
      if (_cells[i].text != ch) {
        _cells[i].text = ch;
      }
    }
    if (mounted) setState(() {});
  }

  void _pushToParent() {
    final code = _cells.map((c) => c.text).join();
    if (widget.controller.text != code) {
      widget.controller.text = code;
    }
  }

  void _onChanged(int index, String value) {
    if (!widget.enabled) return;

    final digit = value.replaceAll(RegExp(r'\D'), '');
    if (digit.length > 1) {
      _fillFromPaste(index, digit);
      return;
    }

    _cells[index].text = digit;
    _pushToParent();

    if (digit.isNotEmpty && index < widget.length - 1) {
      _nodes[index + 1].requestFocus();
    }
    setState(() {});
  }

  void _fillFromPaste(int start, String raw) {
    final digits = raw.replaceAll(RegExp(r'\D'), '');
    var i = start;
    for (var j = 0; j < digits.length && i < widget.length; j++, i++) {
      _cells[i].text = digits[j];
    }
    _pushToParent();
    if (i < widget.length) {
      _nodes[i].requestFocus();
    } else {
      _nodes[widget.length - 1].unfocus();
    }
    setState(() {});
  }

  KeyEventResult _onKey(int index, KeyEvent event) {
    if (event is! KeyDownEvent) return KeyEventResult.ignored;
    if (event.logicalKey == LogicalKeyboardKey.backspace &&
        _cells[index].text.isEmpty &&
        index > 0) {
      _nodes[index - 1].requestFocus();
      _cells[index - 1].clear();
      _pushToParent();
      setState(() {});
      return KeyEventResult.handled;
    }
    return KeyEventResult.ignored;
  }

  @override
  void dispose() {
    widget.controller.removeListener(_syncFromParentListener);
    for (final n in _nodes) {
      n.dispose();
    }
    for (final c in _cells) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: List.generate(widget.length, (i) {
            final filled = _cells[i].text.isNotEmpty;
            return Expanded(
              child: Padding(
                padding: EdgeInsets.only(
                  left: i == 0 ? 0 : 6,
                  right: i == widget.length - 1 ? 0 : 6,
                ),
                child: Focus(
                  onKeyEvent: (node, event) => _onKey(i, event),
                  child: TextField(
                    controller: _cells[i],
                    focusNode: _nodes[i],
                    enabled: widget.enabled,
                    textAlign: TextAlign.center,
                    keyboardType: TextInputType.number,
                    textInputAction: i == widget.length - 1
                        ? TextInputAction.done
                        : TextInputAction.next,
                    autofillHints: i == 0
                        ? const [AutofillHints.oneTimeCode]
                        : null,
                    // Izinkan seluruh kode masuk ke satu cell saat paste.
                    // _onChanged akan membagikannya ke semua kotak.
                    maxLength: widget.length,
                    style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: _OtpColors.textPrimary,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.digitsOnly,
                      LengthLimitingTextInputFormatter(widget.length),
                    ],
                    decoration: InputDecoration(
                      counterText: '',
                      filled: true,
                      fillColor: widget.enabled
                          ? _OtpColors.softPanel
                          : _OtpColors.softPanel.withValues(alpha: 0.5),
                      contentPadding: const EdgeInsets.symmetric(vertical: 14),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: BorderSide(
                          color: _OtpColors.muted.withValues(alpha: 0.35),
                        ),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: BorderSide(
                          color: filled
                              ? _OtpColors.primary.withValues(alpha: 0.55)
                              : _OtpColors.muted.withValues(alpha: 0.35),
                        ),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(
                          color: _OtpColors.primary,
                          width: 1.6,
                        ),
                      ),
                    ),
                    onChanged: (v) => _onChanged(i, v),
                  ),
                ),
              ),
            );
          }),
        ),
        const SizedBox(height: 8),
        Text(
          'Masukkan ${widget.length} digit kode OTP dari email.',
          style: TextStyle(fontSize: 11, color: _OtpColors.muted),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }
}

abstract final class _OtpColors {
  static const textPrimary = Color(0xFFF8FAFC);
  static const softPanel = Color(0xFF2A2A2A);
  static const muted = Color(0xFF64748B);
  static const primary = Color(0xFF00AED6);
}
