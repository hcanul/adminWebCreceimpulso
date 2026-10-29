<template>
  <q-page class="q-pa-md documents-page"
    ><h1>Documentos del expediente</h1>
    <p>
      Emite una versión para impresión. El contrato y el pagaré para revisión todavía no son
      formatos para firma. Las versiones anteriores y sus archivos firmados se conservan.
    </p>
    <template v-if="!id"
      ><q-card flat bordered
        ><q-card-section
          ><h2>Elige el expediente o crédito</h2>
          <p>
            Desde Clientes abre «Documentos» para generar el expediente y el acta de instalación.
            Desde un crédito autorizado abre «Documentos» para emitir su acta de autorización.
          </p>
          <q-btn
            v-if="auth.can('intake.access')"
            to="/clientes"
            label="Clientes y expedientes"
            color="primary"
            no-caps /><q-btn
            v-if="auth.can('credit.view')"
            to="/solicitudes-credito"
            label="Solicitudes de crédito"
            flat
            no-caps /></q-card-section></q-card
    ></template>
    <template v-else
      ><q-btn
        flat
        no-caps
        icon="arrow_back"
        label="Volver"
        :to="type === 'file' ? '/clientes' : `/solicitudes-credito/${id}`"
      />
      <q-banner v-if="error" role="alert" class="bg-red-1 text-negative"
        >{{ error }}<q-btn flat label="Reintentar" @click="load" /></q-banner
      ><q-linear-progress v-if="loading" indeterminate />
      <div v-if="data" class="q-my-md">
        <q-banner v-if="!data.can_issue" class="bg-orange-1"
          >Primero autoriza el crédito para emitir el acta.</q-banner
        >
        <q-banner v-if="type === 'credit'" class="bg-orange-1 q-mb-md"
          >El contrato y el pagaré grupal para revisión reúnen las condiciones autorizadas. Faltan
          los datos de celebración y la revisión del texto definitivo antes de usarlo para
          firma.</q-banner
        >
        <div class="row q-gutter-sm">
          <q-btn
            v-for="kind in kinds"
            :key="kind.value"
            color="primary"
            no-caps
            :label="`Generar ${kind.label}`"
            :disable="busy || !data.can_issue"
            @click="issue(kind.value)"
          />
        </div>
        <p class="q-mt-md">
          Versión actual del registro: {{ data.source_version }}. Generar otra vez la misma versión
          recupera el documento existente.
        </p>
        <q-card v-for="doc in data.documents" :key="doc.id" flat bordered class="q-mb-md"
          ><q-card-section
            ><h2>{{ labels[doc.kind] }} · Versión {{ doc.source_version }}</h2>
            <p>
              {{ doc.created_at }} · {{ doc.actor_name }} · Plantilla {{ doc.template_version }}
            </p>
            <p class="text-caption">{{ doc.id }}</p>
            <q-badge v-if="doc.source_version !== data.source_version" color="orange-9"
              >Versión anterior</q-badge
            ><q-btn
              flat
              no-caps
              icon="print"
              label="Ver e imprimir"
              :disable="busy"
              @click="view(doc.id)" />
            <div
              v-if="!['contract_review', 'promissory_review'].includes(doc.kind)"
              class="row items-center q-gutter-md q-mt-md"
            >
              <q-file
                :model-value="uploads[doc.id] ?? null"
                @update:model-value="(value) => (uploads[doc.id] = value as File | null)"
                outlined
                accept="application/pdf"
                label="PDF firmado"
                hint="Máximo 10 MB"
                :disable="busy"
                style="min-width: 240px; flex: 1"
              /><q-btn
                outline
                no-caps
                label="Adjuntar firmado"
                :disable="busy || !uploads[doc.id]"
                @click="attach(doc.id)"
              />
            </div>
            <p
              v-if="!['contract_review', 'promissory_review'].includes(doc.kind)"
              class="text-caption"
            >
              Adjuntar no valida la firma ni sustituye la revisión de Gestión. Para una corrección,
              agrega otro archivo; el anterior se conserva.
            </p>
            <div v-for="file in doc.signed_files" :key="file.id" class="q-mt-sm">
              {{ file.created_at }} · {{ file.actor_name
              }}<q-btn
                flat
                no-caps
                icon="download"
                label="Descargar firmado"
                :disable="busy"
                @click="download(file.id)"
              /></div></q-card-section
        ></q-card>
        <p v-if="!data.documents.length">Aún no se han emitido documentos.</p>
      </div></template
    >
    <q-dialog v-model="preview" maximized
      ><q-card class="column"
        ><q-card-section class="row items-center q-gutter-sm"
          ><q-btn
            color="primary"
            no-caps
            icon="print"
            label="Imprimir o guardar PDF"
            :disable="!frameReady || busy"
            @click="print"
          /><q-btn flat label="Cerrar" v-close-popup /><span
            >El contrato conserva su versión; las claves temporales solo aparecen en esta impresión.</span
          ></q-card-section
        ><iframe
          ref="frame"
          title="Documento para firma"
          :srcdoc="html"
          sandbox="allow-same-origin allow-modals"
          @load="frameReady = true"
          style="flex: 1; width: 100%; border: 0" /></q-card
    ></q-dialog>
  </q-page>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useDocumentsStore } from '../stores/documents.store';
import { documentsService } from '../services/documents.service';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { useAuthStore } from '../stores/auth.store';
const route = useRoute(),
  store = useDocumentsStore(),
  auth = useAuthStore();
const { data, loading, error } = storeToRefs(store);
const id = computed(() => String((route.params as { id?: string }).id ?? '')),
  type = computed(() => String((route.params as { type?: string }).type ?? ''));
const labels: Record<string, string> = {
  dossier: 'Expediente del cliente',
  installation: 'Acta de instalación',
  authorization: 'Acta de autorización',
  schedule: 'Calendario de pagos',
  contract_review: 'Contrato para revisión',
  promissory_review: 'Pagaré grupal para revisión',
};
const kinds = computed(() =>
  (type.value === 'file'
    ? ['dossier', 'installation']
    : ['authorization', 'schedule', 'contract_review', 'promissory_review']
  ).map((value) => ({
    value,
    label: labels[value],
  })),
);
const busy = ref(false),
  preview = ref(false),
  html = ref(''),
  previewId = ref(''),
  printPrepared = ref(false),
  frame = ref<HTMLIFrameElement | null>(null),
  frameReady = ref(false),
  uploads = ref<Record<string, File | null>>({});
let disposed = false;
function load() {
  return id.value ? store.load(type.value, id.value) : Promise.resolve();
}
async function run(work: () => Promise<void>) {
  if (busy.value) return;
  busy.value = true;
  try {
    await work();
  } catch (e) {
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
async function view(docId: string) {
  await run(async () => {
    const result = await documentsService.show(docId);
    if (disposed) return;
    previewId.value = docId;
    printPrepared.value = false;
    html.value = result.html;
    frameReady.value = false;
    preview.value = true;
  });
}
async function issue(kind: string) {
  await run(async () => {
    const result = await documentsService.issue(type.value, id.value, kind);
    await load();
    const doc = await documentsService.show(result.id);
    if (disposed) return;
    previewId.value = result.id;
    printPrepared.value = false;
    html.value = doc.html;
    frameReady.value = false;
    preview.value = true;
  });
}
async function attach(docId: string) {
  const file = uploads.value[docId];
  if (!file) return;
  if (file.size > 10 * 1024 * 1024) return alerts.error('El archivo supera 10 MB.');
  if (
    !(await alerts.confirm(
      'Adjuntar documento firmado',
      'El archivo quedará asociado permanentemente a esta versión. ¿Continuar?',
    ))
  )
    return;
  await run(async () => {
    await documentsService.attach(docId, file);
    uploads.value[docId] = null;
    await load();
    await alerts.success('Archivo firmado conservado');
  });
}
async function download(fileId: string) {
  await run(async () => {
    const blob = await documentsService.download(fileId);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `firmado-${fileId}.pdf`;
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
}
async function print() {
  if (printPrepared.value) { frame.value?.contentWindow?.print(); return; }
  await run(async () => {
    const result = await documentsService.print(previewId.value);
    if (disposed || !preview.value) return;
    if (html.value === result.html) {
      printPrepared.value = true;
      frame.value?.contentWindow?.print();
      return;
    }
    frameReady.value = false;
    printPrepared.value = true;
    html.value = result.html;
  });
}
watch(frameReady, (ready) => { if (ready && printPrepared.value && preview.value) frame.value?.contentWindow?.print(); });
watch(preview, (open) => { if (!open) { html.value = ''; previewId.value = ''; printPrepared.value = false; } });
watch(
  () => route.fullPath,
  () => {
    preview.value = false;
    html.value = '';
    uploads.value = {};
    store.clear();
    void load();
  },
  { immediate: true },
);
onBeforeUnmount(() => {
  disposed = true;
  store.clear();
  html.value = '';
});
</script>
<style scoped>
.documents-page {
  max-width: 1200px;
  margin: auto;
}
h1 {
  font-size: 26px;
  line-height: 1.3;
  font-weight: 700;
}
h2 {
  font-size: 18px;
  line-height: 1.4;
  font-weight: 600;
}
.text-caption {
  overflow-wrap: anywhere;
}
</style>
