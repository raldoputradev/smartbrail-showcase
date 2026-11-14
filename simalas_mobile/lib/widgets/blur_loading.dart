part of '../main.dart';

/// Overlay blur di atas konten halaman saat [loading].
class BlurPageLoader extends StatelessWidget {
  const BlurPageLoader({
    super.key,
    required this.loading,
    required this.child,
    this.message = 'Memuat…',
  });

  final bool loading;
  final Widget child;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        child,
        AnimatedSwitcher(
          duration: const Duration(milliseconds: 280),
          child: loading
              ? ClipRect(
                  key: const ValueKey('blur-overlay'),
                  child: BackdropFilter(
                    filter: ImageFilter.blur(sigmaX: 10, sigmaY: 10),
                    child: Container(
                      color: Colors.white.withValues(alpha: 0.55),
                      child: Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const ModernLoadIndicator(size: 56),
                            const SizedBox(height: 16),
                            Text(
                              message,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                color: AppColors.muted,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                )
              : const SizedBox.shrink(key: ValueKey('no-blur')),
        ),
      ],
    );
  }
}
