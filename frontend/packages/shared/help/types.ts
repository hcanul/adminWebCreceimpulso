export interface HelpGuide {
  targets?: Array<{ selector: string; title: string; description: string }>;
  id: string;
  title: string;
  purpose: string;
  steps: string[];
  important: string;
  primaryLabel: string;
}
export interface HelpStep {
  element?: Element;
  popover: { title: string; description: string };
}
export interface HelpTour {
  drive: () => void;
  destroy: () => void;
}
export function helpTourConfig(steps: HelpStep[], onDestroyed: () => void) {
  return {
    steps,
    showProgress: true,
    progressText: "{{current}} de {{total}}",
    nextBtnText: "Siguiente",
    prevBtnText: "Anterior",
    doneBtnText: "Finalizar",
    allowClose: true,
    allowKeyboardControl: true,
    disableActiveInteraction: true,
    animate: !window.matchMedia("(prefers-reduced-motion: reduce)").matches,
    popoverClass: "crece-tour",
    overlayColor: "#262b38",
    overlayOpacity: 0.65,
    stageRadius: 10,
    onPopoverRender: (popover: { closeButton: HTMLButtonElement }) =>
      popover.closeButton.setAttribute("aria-label", "Cerrar recorrido"),
    onDestroyed,
  };
}
