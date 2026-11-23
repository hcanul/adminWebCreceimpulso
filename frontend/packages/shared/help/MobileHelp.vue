<template>
  <q-btn
    flat
    no-caps
    color="primary"
    icon="help_outline"
    label="Ayuda"
    data-tour="help"
    aria-label="Ayuda del módulo"
    class="module-help-button no-print"
    @click="show = true"
    ><q-tooltip>Cómo funciona {{ help.title }}</q-tooltip></q-btn
  >
  <q-dialog v-model="show" @hide="dialogHidden">
    <q-card
      class="module-help-card"
      role="document"
      aria-labelledby="module-help-title"
    >
      <q-card-section class="row items-center no-wrap">
        <q-icon
          name="help_outline"
          color="primary"
          size="28px"
          class="q-mr-md"
        />
        <div class="col">
          <p class="module-help-eyebrow">GUÍA DE USO</p>
          <h2 id="module-help-title">{{ help.title }}</h2>
        </div>
        <q-btn
          flat
          round
          icon="close"
          aria-label="Cerrar ayuda"
          v-close-popup
        />
      </q-card-section>
      <q-separator />
      <q-card-section>
        <p class="module-help-purpose">{{ help.purpose }}</p>
        <h3>Cómo utilizar este módulo</h3>
        <ol class="module-help-steps">
          <li v-for="step in help.steps" :key="step">{{ step }}</li>
        </ol>
        <q-banner rounded class="bg-grey-2 module-help-important">
          <template #avatar
            ><q-icon name="info_outline" color="primary"
          /></template>
          {{ help.important }}
        </q-banner>
        <p class="text-caption text-grey-8 q-mt-md q-mb-none">
          Las acciones disponibles dependen de los permisos de tu cuenta. El
          recorrido no guarda, autoriza ni modifica registros.
        </p>
      </q-card-section>
      <q-card-actions align="right" class="module-help-actions">
        <q-btn
          v-if="generalTour"
          flat
          no-caps
          label="Guía general"
          @click="requestTour('general')"
        />
        <q-btn flat no-caps label="Cerrar" v-close-popup />
        <q-btn
          color="primary"
          no-caps
          icon="explore"
          label="Ver recorrido del módulo"
          @click="requestTour('module')"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useQuasar } from "quasar";
import type { HelpGuide, HelpStep, HelpTour } from "./types";

const props = defineProps<{
  guide: HelpGuide;
  createTour: (steps: HelpStep[], done: () => void) => Promise<HelpTour>;
  generalTour?: () => void | Promise<void>;
}>();
const route = useRoute();
const q = useQuasar();
const help = computed(() => props.guide);
const show = ref(false);
let queued: "module" | "general" | null = null;
let active: HelpTour | undefined;
let revision = 0;
let alive = true;
let opening = false;
function stop() {
  revision++;
  active?.destroy();
  active = undefined;
}
function requestTour(kind: "module" | "general") {
  queued = kind;
  show.value = false;
}
async function dialogHidden() {
  const kind = queued;
  queued = null;
  if (kind === "general") await props.generalTour?.();
  if (kind === "module") await startModuleTour();
}
async function startModuleTour() {
  if (opening || !alive) return;
  stop();
  opening = true;
  const attempt = revision;
  const guide = help.value;
  const escape = (text: string) =>
    text.replace(
      /[&<>"']/g,
      (character) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[character]!,
    );
  try {
    await nextTick();
    if (!alive || attempt !== revision) return;
    const scope = document.querySelector("[data-module-help-content]");
    const find = (selector: string) =>
      Array.from(scope?.querySelectorAll<HTMLElement>(selector) ?? []).find(
        (element) =>
          element.getClientRects().length > 0 &&
          !element.closest('[aria-hidden="true"]'),
      );
    const heading = find("h1");
    const steps: HelpStep[] = [
      {
        ...(heading ? { element: heading } : {}),
        popover: {
          title: escape(guide.title),
          description: escape(guide.purpose),
        },
      },
    ];
    for (const target of guide.targets ?? []) {
      const element = find(target.selector);
      if (element)
        steps.push({
          element,
          popover: {
            title: escape(target.title),
            description: escape(target.description),
          },
        });
    }
    const fields = find(".filters, .q-form, .capture-sections, .q-field");
    if (fields)
      steps.push({
        element: fields,
        popover: {
          title: "Campos y filtros",
          description:
            "Completa los campos visibles o selecciona los filtros que necesites. Revisa los datos y las validaciones antes de continuar.",
        },
      });
    const records = find(
      ".q-table__container, .table-scroll, table, .q-list, .credit-facts, .metrics",
    );
    if (records)
      steps.push({
        element: records,
        popover: {
          title: "Revisa la información",
          description: escape(guide.steps[0] ?? guide.purpose),
        },
      });
    const primary =
      guide.primaryLabel &&
      Array.from(scope?.querySelectorAll<HTMLElement>(".q-btn") ?? []).find(
        (element) =>
          element.innerText.includes(guide.primaryLabel) &&
          element.getClientRects().length > 0 &&
          !element.hasAttribute("disabled"),
      );
    if (primary)
      steps.push({
        element: primary,
        popover: {
          title: escape(guide.primaryLabel),
          description:
            "Este es un acceso a la siguiente acción del módulo. El recorrido solo lo señala; al cerrar la guía puedes utilizarlo cuando hayas revisado los datos.",
        },
      });
    steps.push({
      popover: {
        title: "Procedimiento y puntos importantes",
        description:
          "<ol>" +
          guide.steps.map((step) => "<li>" + escape(step) + "</li>").join("") +
          "</ol><p>" +
          escape(guide.important) +
          "</p>",
      },
    });
    const tour = await props.createTour(steps, () => {
      active = undefined;
    });
    if (!alive || attempt !== revision) {
      tour.destroy();
      return;
    }
    active = tour;
    active.drive();
  } catch {
    if (alive && attempt === revision) {
      q.notify({
        type: "warning",
        message:
          "No se pudo abrir el recorrido. Puedes consultar las instrucciones en Ayuda.",
      });
      show.value = true;
    }
  } finally {
    opening = false;
  }
}
watch(
  () => route.fullPath,
  () => {
    queued = null;
    show.value = false;
    stop();
  },
);
onBeforeUnmount(() => {
  alive = false;
  queued = null;
  stop();
});
</script>
<style scoped lang="scss">
.module-help-button {
  min-height: 44px;
}
.module-help-card {
  width: 620px;
  max-width: calc(100vw - 24px);
  border-radius: 16px;
  color: #262b38;
}
.module-help-eyebrow {
  margin: 0 0 4px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.5px;
  color: #811335;
}
.module-help-card h2 {
  margin: 0;
  font-size: 22px;
  line-height: 1.3;
  font-weight: 700;
}
.module-help-card h3 {
  font-size: 16px;
  font-weight: 650;
  line-height: 1.5;
  margin: 20px 0 10px;
}
.module-help-purpose,
.module-help-steps,
.module-help-important {
  font-size: 14px;
  line-height: 1.65;
}
.module-help-steps {
  padding-left: 24px;
}
.module-help-steps li {
  padding-left: 4px;
  margin-bottom: 12px;
}
.module-help-actions {
  padding: 12px 16px 20px;
  gap: 8px;
}
.module-help-actions :deep(.q-btn) {
  min-height: 44px;
}
@media (max-width: 600px) {
  .module-help-card h2 {
    font-size: 19px;
  }
}
@media print {
  .module-help-button,
  .module-help-card {
    display: none !important;
  }
}
</style>
