import { useCallback, useEffect, useRef, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Camera, ScanLine, X, Loader2, AlertCircle, Globe2, Barcode, Hash, Zap, ZapOff, RefreshCw, Copy, CheckCircle2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { apiBarcodeScan, apiOcrScan } from "@/lib/api";
import type { Medicine } from "@/lib/medicine-store";
import { useAuth } from "@/contexts/auth-context";
import { t } from "@/lib/i18n";
import { toast } from "sonner";

type ScanPayload = {
  name: string;
  genericName: string;
  dosage: string;
  frequency: string;
  purpose: string;
  purposeFil: string;
  precautions: string;
  precautionsFil: string;
  notes: string;
  /** Newline-separated reference lines (often include https URLs). */
  references: string;
  referencesFil: string;
  confidence: number;
  source: string;
  sourceLabel: string;
};

function mapApiToScanPayload(
  data: Record<string, string | boolean | undefined>,
  source: string
): ScanPayload {
  const name = String(data.name || "Unknown");
  const dosage = String(data.dosage || "");
  const frequency = String(data.frequency || "Follow your prescription label");
  const purposeEn = String(data.purpose_en || "");
  const purposeFil = String(data.purpose_fil || purposeEn);
  const precEn = String(data.precautions_en || "");
  const precFil = String(data.precautions_fil || precEn);
  const referencesEn = String(data.references_en || "").trim();
  const referencesFil = String(data.references_fil || referencesEn).trim();
  let confidence = 72;
  let sourceLabel = "Lookup";
  if (source === "open_data") {
    confidence = 92;
    sourceLabel = "Online (RxNav / OpenFDA)";
  } else if (source === "barcode") {
    confidence = 95;
    sourceLabel = "Barcode → RxNav / OpenFDA";
  } else if (source === "ph_local_registry") {
    confidence = 100;
    sourceLabel = "Verified Local Registry (PharSayo)";
  } else if (source === "openfoodfacts_ph") {
    confidence = 85;
    sourceLabel = "Barcode → Open Food Facts (PH)";
  } else if (source === "barcode_upcitemdb") {
    confidence = 72;
    sourceLabel = "Barcode → web product lookup";
  } else if (source === "openfoodfacts_search") {
    confidence = 74;
    sourceLabel = "Barcode → Open Food Facts (search)";
  } else if (source === "openproductsfacts" || source === "openproductsfacts_search") {
    confidence = 84;
    sourceLabel =
      source === "openproductsfacts_search"
        ? "Barcode → Open Products Facts (search)"
        : "Barcode → Open Products Facts";
  } else if (source === "barcode_barcodelist") {
    confidence = 78;
    sourceLabel = "Barcode → barcode-list.com (+ FDA if matched)";
  } else if (source === "barcode_placeholder") {
    confidence = 45;
    sourceLabel = "Barcode saved — verify on package";
  } else if (source === "internal_db" || source === "internal_db_ocr_match") {
    confidence = 88;
    sourceLabel = "Local reference list";
  } else if (source === "openfoodfacts" || source === "openbeautyfacts" || source === "openpetfoodfacts") {
    confidence = 78;
    const labels: Record<string, string> = {
      openfoodfacts: "Barcode → Open Food Facts",
      openbeautyfacts: "Barcode → Open Beauty Facts",
      openpetfoodfacts: "Barcode → Open Pet Food Facts",
    };
    sourceLabel = labels[source] ?? "Barcode → open product data";
  } else if (source === "heuristic") {
    confidence = 55;
    sourceLabel = "Best guess — verify with your doctor";
  } else if (source === "wikidata_gtin") {
    confidence = 80;
    sourceLabel = "Barcode → Wikidata (+ FDA if matched)";
  } else if (source === "web_search_fallback") {
    confidence = 48;
    sourceLabel = "Public databases & search links";
  }

  return {
    name,
    genericName: name,
    dosage,
    frequency,
    purpose: purposeEn,
    purposeFil,
    precautions: precEn,
    precautionsFil: precFil,
    notes: "",
    references: referencesEn,
    referencesFil: referencesFil,
    confidence,
    source,
    sourceLabel,
  };
}

async function lookupMedicineByBarcode(
  barcode: string,
  errors: { empty: string; notFound: string },
  lang: "en" | "fil",
): Promise<{ payload: ScanPayload } | { error: string }> {
  const trimmed = barcode.trim();
  if (!trimmed) {
    return { error: errors.empty };
  }
  const res = await apiBarcodeScan(trimmed, lang);
  if (!res.success || !res.data) {
    return { error: res.message || errors.notFound };
  }
  const payload = mapApiToScanPayload(res.data as Record<string, string | boolean | undefined>, res.source || "unknown");
  return { payload };
}

interface Props {
  /** If omitted, scan results are view-only (e.g. patient — doctor adds meds to the list). */
  onAddMedicine?: (med: Omit<Medicine, "id" | "color" | "icon" | "takenToday" | "streak" | "addedAt" | "scheduleSlots">) => void | Promise<void>;
  canSave?: boolean;
}

export default function MedicineScanner({ onAddMedicine, canSave = true }: Props) {
  const { lang, user } = useAuth();
  const isPatient = user?.role === "patient";
  const isAuthorizedToSave = user?.role === "doctor" || user?.role === "admin";
  const allowSaveToList = isAuthorizedToSave && canSave;
  const [isScanning, setIsScanning] = useState(false);
  const [torchOn, setTorchOn] = useState(false);
  const [hasTorch, setHasTorch] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [scanResult, setScanResult] = useState<ScanPayload | null>(null);
  const [lastScannedBarcode, setLastScannedBarcode] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [manualBarcode, setManualBarcode] = useState("");
  const [manualBusy, setManualBusy] = useState(false);
  const [manualName, setManualName] = useState("");
  const [nameBusy, setNameBusy] = useState(false);
  const [barcodeCopied, setBarcodeCopied] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const videoRef = useRef<HTMLVideoElement>(null);
  const scanCancelledRef = useRef(false);
  const streamRef = useRef<MediaStream | null>(null);
  const scannerControlsRef = useRef<{ stop: () => void } | null>(null);
  const lastScannedTextRef = useRef<string>("");

  const runLookup = useCallback(
    async (barcode: string) => {
      setIsProcessing(true);
      setError(null);
      try {
        const out = await lookupMedicineByBarcode(
          barcode,
          {
            empty: t(lang, "scanner.errorNoBarcode"),
            notFound: t(lang, "scanner.errorNotFound"),
          },
          lang,
        );
        if ("error" in out) {
          setError(
            out.error.includes("not found") || out.error.includes("hindi nahanap")
              ? `${out.error} ${t(lang, "scanner.tryNameLookupInstead")}`
              : out.error
          );
          // Auto-fill manual barcode field so user can retry or save
          setManualBarcode(barcode.trim());
          return;
        }

        setLastScannedBarcode(barcode.trim());
        setScanResult(out.payload);
      } catch (e) {
        setError(e instanceof Error ? e.message : t(lang, "scanner.lookupErrorGeneric"));
        setManualBarcode(barcode.trim());
      } finally {
        setIsProcessing(false);
      }
    },
    [lang],
  );

  useEffect(() => {
    if (!isScanning) return;
    scanCancelledRef.current = false;
    streamRef.current = null;
    scannerControlsRef.current = null;
    lastScannedTextRef.current = "";

    void (async () => {
      try {
        // Explicitly check camera permission first
        if (typeof navigator.permissions !== "undefined" && (navigator.permissions as any).query) {
          try {
            const status = await navigator.permissions.query({ name: "camera" as any });
            console.log("Camera permission status:", status.state);
          } catch (e) {
            console.warn("Permissions API check failed:", e);
          }
        }

        const stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { ideal: "environment" },
            width: { ideal: 1920 },
            height: { ideal: 1080 },
          },
          audio: false,
        });
        if (scanCancelledRef.current) {
          stream.getTracks().forEach((t) => t.stop());
          return;
        }
        streamRef.current = stream;

        // Check for torch capability
        const track = stream.getVideoTracks()[0];
        if (track) {
          const caps = track.getCapabilities?.();
          if (caps && (caps as any).torch) {
            setHasTorch(true);
          }
        }

        const v = videoRef.current;
        if (!v) {
          stream.getTracks().forEach((t) => t.stop());
          streamRef.current = null;
          return;
        }
        v.srcObject = stream;
        await v.play().catch(() => {});

        // Import ZXing with Philippine barcode format hints (EAN-13 is the dominant PH format)
        const { BrowserMultiFormatReader } = await import("@zxing/browser");
        const { DecodeHintType, BarcodeFormat } = await import("@zxing/library");

        const hints = new Map();
        hints.set(DecodeHintType.POSSIBLE_FORMATS, [
          BarcodeFormat.EAN_13,    // Primary format for PH medicines (e.g. 4807788...)
          BarcodeFormat.EAN_8,     // Short barcodes on small packages
          BarcodeFormat.UPC_A,     // US-standard (some imported PH medicines)
          BarcodeFormat.UPC_E,     // Compressed UPC for small packages
          BarcodeFormat.CODE_128,  // Hospital/pharmacy internal codes
          BarcodeFormat.CODE_39,   // Some older PH hospital systems
          BarcodeFormat.ITF,       // Used on some cartons/outer packaging
          BarcodeFormat.DATA_MATRIX, // Some modern PH prescription labels
          BarcodeFormat.QR_CODE,   // Some digital prescriptions / medicine apps
        ]);
        // Try harder — attempt multiple times per frame
        hints.set(DecodeHintType.TRY_HARDER, true);

        const reader = new BrowserMultiFormatReader(hints);
        const ctl = await reader.decodeFromVideoElement(v, (result, _err, c) => {
          if (scanCancelledRef.current || !result) return;
          const text = result.getText().trim();
          if (!text) return;
          // Debounce: skip if same barcode was already sent for lookup
          if (text === lastScannedTextRef.current) return;
          lastScannedTextRef.current = text;
          c.stop();
          scannerControlsRef.current = null;
          setIsScanning(false);
          void runLookup(text);
        });
        if (scanCancelledRef.current) {
          ctl.stop();
          return;
        }
        scannerControlsRef.current = ctl;
      } catch {
        setError(t(lang, "scanner.cameraError"));
        setIsScanning(false);
      }
    })();

    return () => {
      scanCancelledRef.current = true;
      scannerControlsRef.current?.stop();
      scannerControlsRef.current = null;
      streamRef.current?.getTracks().forEach((t) => t.stop());
      streamRef.current = null;
      const v = videoRef.current;
      if (v) v.srcObject = null;
    };
  }, [isScanning, runLookup, lang]);

  const handleManualLookup = useCallback(async () => {
    const code = manualBarcode.trim();
    if (!code) {
      setError(t(lang, "scanner.manualEmpty"));
      return;
    }
    setError(null);
    setManualBusy(true);
    try {
      const out = await lookupMedicineByBarcode(
        code,
        {
          empty: t(lang, "scanner.errorNoBarcode"),
          notFound: t(lang, "scanner.errorNotFound"),
        },
        lang,
      );
      if ("error" in out) {
        setError(out.error);
      } else {
        setLastScannedBarcode(code);
        setScanResult(out.payload);
        setManualBarcode("");
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : t(lang, "scanner.lookupErrorGeneric"));
    } finally {
      setManualBusy(false);
    }
  }, [manualBarcode, lang]);

  const handleNameLookup = useCallback(async () => {
    const q = manualName.trim();
    if (!q) {
      setError(t(lang, "scanner.nameLookupEmpty"));
      return;
    }
    setError(null);
    setNameBusy(true);
    try {
      const res = await apiOcrScan({ extracted_text: q, candidate_names: [q] });
      if (!res.success || !res.data) {
        setError(res.message || t(lang, "scanner.errorNotFound"));
        return;
      }
      const payload = mapApiToScanPayload(res.data as Record<string, string | boolean | undefined>, res.source || "open_data");
      setLastScannedBarcode(null);
      setScanResult(payload);
      setManualName("");
    } catch (e) {
      setError(e instanceof Error ? e.message : t(lang, "scanner.lookupErrorGeneric"));
    } finally {
      setNameBusy(false);
    }
  }, [manualName, lang]);

  const decodeBarcodeFromFile = useCallback(
    async (file: File) => {
      setIsScanning(false);
      setError(null);
      setIsProcessing(true);
      const url = URL.createObjectURL(file);
      try {
        const { BrowserMultiFormatReader } = await import("@zxing/browser");
        const { DecodeHintType, BarcodeFormat } = await import("@zxing/library");

        const hints = new Map();
        hints.set(DecodeHintType.POSSIBLE_FORMATS, [
          BarcodeFormat.EAN_13,
          BarcodeFormat.EAN_8,
          BarcodeFormat.UPC_A,
          BarcodeFormat.UPC_E,
          BarcodeFormat.CODE_128,
          BarcodeFormat.CODE_39,
          BarcodeFormat.ITF,
          BarcodeFormat.DATA_MATRIX,
          BarcodeFormat.QR_CODE,
        ]);
        hints.set(DecodeHintType.TRY_HARDER, true);

        const reader = new BrowserMultiFormatReader(hints);

        let result;
        try {
          result = await reader.decodeFromImageUrl(url);
        } catch (e) {
          console.warn("ZXing decodeFromImageUrl failed, trying canvas with preprocessing...", e);

          result = await new Promise<any>((resolve, reject) => {
            const img = new Image();
            img.onload = async () => {
              try {
                const canvas = document.createElement("canvas");
                const ctx = canvas.getContext("2d");
                if (!ctx) {
                  reject(new Error("Could not get canvas context"));
                  return;
                }

                // Scale up small images for better detection (many phone barcode photos are fine but some thumbnails are too small)
                const minDim = 800;
                const maxDim = 2048;
                let width = img.width;
                let height = img.height;
                if (width < minDim || height < minDim) {
                  const ratio = Math.max(minDim / width, minDim / height);
                  width = Math.round(width * ratio);
                  height = Math.round(height * ratio);
                } else if (width > maxDim || height > maxDim) {
                  const ratio = Math.min(maxDim / width, maxDim / height);
                  width = Math.round(width * ratio);
                  height = Math.round(height * ratio);
                }

                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);

                // Try 1: normal image
                try {
                  const decoded = await reader.decodeFromCanvas(canvas);
                  resolve(decoded);
                  return;
                } catch (_e1) { /* fall through */ }

                // Try 2: higher contrast (helps with faded/glossy packaging)
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                for (let i = 0; i < data.length; i += 4) {
                  // Convert to grayscale with high contrast
                  const gray = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                  const contrast = gray > 128 ? 255 : 0;
                  data[i] = contrast;
                  data[i + 1] = contrast;
                  data[i + 2] = contrast;
                }
                ctx.putImageData(imageData, 0, 0);
                try {
                  const decoded = await reader.decodeFromCanvas(canvas);
                  resolve(decoded);
                  return;
                } catch (_e2) { /* fall through */ }

                reject(new Error("Could not detect a barcode in this image"));
              } catch (err) {
                reject(err);
              }
            };
            img.onerror = () => reject(new Error("Failed to load image"));
            img.src = url;
          });
        }

        const text = result.getText().trim();
        if (!text) {
          setError(t(lang, "scanner.imageNoBarcode"));
          return;
        }
        await runLookup(text);
      } catch (err) {
        console.error("Barcode file decode error:", err);
        setError(t(lang, "scanner.imageNoBarcodeDetail"));
      } finally {
        URL.revokeObjectURL(url);
        setIsProcessing(false);
      }
    },
    [runLookup, lang]
  );

  const handleFileUpload = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      if (!file) return;
      void decodeBarcodeFromFile(file);
      e.target.value = "";
    },
    [decodeBarcodeFromFile]
  );

  const handleAddFromScan = () => {
    if (!scanResult || !onAddMedicine) return;
    if (!allowSaveToList) {
      toast.message(t(lang, "scanner.toastDoctorOnly"));
      return;
    }
    void (async () => {
      try {
        await Promise.resolve(
          onAddMedicine({
            name: scanResult.name,
            genericName: scanResult.genericName,
            dosage: scanResult.dosage,
            frequency: scanResult.frequency,
            times: [],
            purpose: scanResult.purpose,
            purposeFil: scanResult.purposeFil,
            precautions: scanResult.precautions,
            precautionsFil: scanResult.precautionsFil,
            notes: [scanResult.notes, scanResult.references].filter(Boolean).join("\n\n— References —\n"),
            scheduleNotesByTime: {},
          })
        );
        setScanResult(null);
        setLastScannedBarcode(null);
        toast.success(t(lang, "scanner.toastAdded"));
      } catch (err) {
        toast.error(err instanceof Error ? err.message : t(lang, "scanner.addFailed"));
      }
    })();
  };

  const toggleTorch = useCallback(async () => {
    const stream = streamRef.current;
    if (!stream) return;
    const track = stream.getVideoTracks()[0];
    if (!track) return;
    try {
      const next = !torchOn;
      await track.applyConstraints({
        advanced: [{ torch: next } as any],
      });
      setTorchOn(next);
    } catch (e) {
      console.warn("Torch toggle error:", e);
    }
  }, [torchOn]);

  const copyBarcodeToClipboard = useCallback(async (barcode: string) => {
    try {
      await navigator.clipboard.writeText(barcode);
      setBarcodeCopied(true);
      setTimeout(() => setBarcodeCopied(false), 2000);
    } catch {
      /* clipboard may not be available */
    }
  }, []);

  return (
    <div className="flex flex-col items-center">
      <AnimatePresence mode="wait">
        {!isScanning && !isProcessing && !scanResult && (
          <motion.div
            key="options"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            className="w-full space-y-6"
          >
            <div className="text-center space-y-2 mb-6">
              <div className="w-20 h-20 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
                <Barcode className="w-10 h-10 text-primary" />
              </div>
              <h2 className="text-xl font-bold text-foreground">{t(lang, "scanner.titleScan")}</h2>
              <p className="text-muted-foreground text-sm max-w-md mx-auto leading-relaxed">{t(lang, "scanner.introBody")}</p>
            </div>

            {error && (
              <div className="rounded-xl bg-destructive/10 text-destructive text-sm p-4 space-y-3">
                <div className="flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
                  <p>{error}</p>
                </div>
                {/* If barcode was auto-filled, show a quick-retry tip */}
                {manualBarcode && (
                  <div className="text-xs text-destructive/80 pl-6">
                    {lang === "en"
                      ? "The barcode number has been filled below — you can try looking it up manually or save it anyway."
                      : "Ang barcode ay naipasok na sa ibaba — maaari mong i-lookup ito nang mano-mano o i-save bilang paalala."}
                  </div>
                )}
              </div>
            )}

            <div className="grid gap-3">
              <Button size="lg" onClick={() => setIsScanning(true)} className="h-14 text-base gap-3 rounded-2xl">
                <Camera className="w-5 h-5" />
                {t(lang, "scanner.openCamera")}
              </Button>
              <Button
                size="lg"
                variant="outline"
                onClick={() => fileInputRef.current?.click()}
                className="h-14 text-base gap-3 rounded-2xl"
              >
                <ScanLine className="w-5 h-5" />
                {t(lang, "scanner.uploadBarcodeImage")}
              </Button>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={handleFileUpload}
              />
            </div>

            <div className="rounded-2xl border border-border bg-card p-4 space-y-3">
              <div className="flex items-center gap-2 text-foreground font-semibold text-sm">
                <Hash className="w-4 h-4 text-primary shrink-0" />
                {t(lang, "scanner.noCameraHint")}
              </div>
              <p className="text-xs text-muted-foreground leading-relaxed">{t(lang, "scanner.manualBarcodeHelp")}</p>
              <div className="space-y-2">
                <Label htmlFor="manual-barcode" className="text-xs text-muted-foreground">
                  {t(lang, "scanner.barcodeNumberLabel")}
                </Label>
                <Input
                  id="manual-barcode"
                  inputMode="numeric"
                  autoComplete="off"
                  placeholder={t(lang, "scanner.barcodePlaceholder")}
                  value={manualBarcode}
                  onChange={(e) => setManualBarcode(e.target.value)}
                  onKeyDown={(e) => { if (e.key === "Enter" && manualBarcode.trim()) void handleManualLookup(); }}
                  className="h-11 rounded-xl text-sm font-mono tracking-wide"
                  disabled={manualBusy}
                />
              </div>
              <Button
                type="button"
                className="w-full h-11 rounded-xl gap-2"
                onClick={() => void handleManualLookup()}
                disabled={manualBusy || !manualBarcode.trim()}
              >
                {manualBusy ? <Loader2 className="w-4 h-4 animate-spin" /> : <Globe2 className="w-4 h-4" />}
                {t(lang, "scanner.lookupCodeButton")}
              </Button>
            </div>

            <div className="rounded-2xl border border-border bg-card p-4 space-y-3">
              <div className="flex items-center gap-2 text-foreground font-semibold text-sm">
                <Globe2 className="w-4 h-4 text-primary shrink-0" />
                {t(lang, "scanner.nameLookupTitle")}
              </div>
              <p className="text-xs text-muted-foreground leading-relaxed">{t(lang, "scanner.nameLookupHelp")}</p>
              <div className="space-y-2">
                <Label htmlFor="manual-name" className="text-xs text-muted-foreground">
                  {t(lang, "scanner.productNameLabel")}
                </Label>
                <Input
                  id="manual-name"
                  autoComplete="off"
                  placeholder={t(lang, "scanner.productNamePlaceholder")}
                  value={manualName}
                  onChange={(e) => setManualName(e.target.value)}
                  onKeyDown={(e) => { if (e.key === "Enter" && manualName.trim()) void handleNameLookup(); }}
                  className="h-11 rounded-xl text-sm"
                  disabled={nameBusy}
                />
              </div>
              <Button
                type="button"
                variant="outline"
                className="w-full h-11 rounded-xl gap-2"
                onClick={() => void handleNameLookup()}
                disabled={nameBusy || !manualName.trim()}
              >
                {nameBusy ? <Loader2 className="w-4 h-4 animate-spin" /> : <Globe2 className="w-4 h-4" />}
                {t(lang, "scanner.nameLookupButton")}
              </Button>
            </div>

            <div className="bg-muted/40 rounded-2xl border border-border p-4 text-xs text-muted-foreground leading-relaxed">
              <p className="font-semibold text-foreground mb-1 flex items-center gap-2">
                <Globe2 className="w-3.5 h-3.5 text-primary" />
                {t(lang, "scanner.howItWorksTitle")}
              </p>
              <p>{t(lang, "scanner.lookupDisclaimer")}</p>
            </div>
          </motion.div>
        )}

        {isScanning && (
          <motion.div
            key="scanning"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="w-full"
          >
            <div className="relative bg-foreground/5 rounded-2xl overflow-hidden aspect-[3/4] flex items-center justify-center">
              <video ref={videoRef} className="absolute inset-0 w-full h-full object-cover" playsInline muted />

              {/* Scanning frame overlay */}
              <div className="absolute inset-8 border-2 border-primary/50 rounded-xl pointer-events-none">
                <div className="absolute top-0 left-0 w-8 h-8 border-t-[3px] border-l-[3px] border-primary rounded-tl-lg" />
                <div className="absolute top-0 right-0 w-8 h-8 border-t-[3px] border-r-[3px] border-primary rounded-tr-lg" />
                <div className="absolute bottom-0 left-0 w-8 h-8 border-b-[3px] border-l-[3px] border-primary rounded-bl-lg" />
                <div className="absolute bottom-0 right-0 w-8 h-8 border-b-[3px] border-r-[3px] border-primary rounded-br-lg" />
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-transparent via-primary to-transparent scanner-line" />
              </div>

              {hasTorch && (
                <Button
                  variant="secondary"
                  size="icon"
                  className="absolute top-4 right-4 rounded-full bg-black/40 hover:bg-black/60 border-none text-white backdrop-blur-sm"
                  onClick={(e) => {
                    e.stopPropagation();
                    void toggleTorch();
                  }}
                >
                  {torchOn ? <ZapOff className="w-5 h-5" /> : <Zap className="w-5 h-5" />}
                </Button>
              )}

              <p className="absolute bottom-3 left-3 right-3 text-center text-xs text-white drop-shadow-md bg-black/50 rounded-lg py-2 px-2">
                {lang === "en"
                  ? "Point camera at the barcode on the medicine box. Hold steady."
                  : "Itutok ang camera sa barcode ng kahon ng gamot. Huwag gumalaw."}
              </p>
            </div>

            <div className="flex gap-3 mt-4">
              <Button variant="outline" onClick={() => setIsScanning(false)} className="flex-1 h-12 rounded-xl">
                <X className="w-4 h-4 mr-2" /> {t(lang, "scanner.cancel")}
              </Button>
              <Button
                variant="outline"
                onClick={() => fileInputRef.current?.click()}
                className="flex-1 h-12 rounded-xl"
              >
                <ScanLine className="w-4 h-4 mr-2" />
                {lang === "en" ? "Upload photo" : "Mag-upload"}
              </Button>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={handleFileUpload}
              />
            </div>
          </motion.div>
        )}

        {isProcessing && (
          <motion.div
            key="processing"
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0 }}
            className="w-full flex flex-col items-center py-12 px-2"
          >
            <div className="w-20 h-20 rounded-2xl bg-primary/10 flex items-center justify-center mb-6">
              <Loader2 className="w-10 h-10 text-primary animate-spin" />
            </div>
            <h3 className="text-lg font-semibold text-foreground mb-3 text-center">{t(lang, "scanner.searchingTitle")}</h3>
            <div className="space-y-2 text-sm text-muted-foreground text-center max-w-sm">
              <p className="flex items-center justify-center gap-2 flex-wrap">
                <Globe2 className="w-4 h-4 shrink-0 text-primary" />
                {lang === "en"
                  ? "Searching Philippine medicine registry and global databases..."
                  : "Hinahanap sa Philippine medicine registry at global databases..."}
              </p>
            </div>
          </motion.div>
        )}

        {scanResult && (
          <motion.div
            key="result"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            className="w-full space-y-4"
          >
            <div className="flex items-center justify-between gap-2 flex-wrap">
              <h3 className="text-lg font-bold text-foreground">{t(lang, "scanner.resultTitle")}</h3>
              <span className={`px-2.5 py-1 text-xs font-semibold rounded-lg shrink-0 max-w-[240px] text-right leading-tight ${
                scanResult.confidence >= 90
                  ? "bg-success/10 text-success"
                  : scanResult.confidence >= 70
                  ? "bg-warning/10 text-warning"
                  : "bg-muted text-muted-foreground"
              }`}>
                ~{Math.round(scanResult.confidence)}% · {scanResult.sourceLabel}
              </span>
            </div>

            {lastScannedBarcode && (
              <div className="rounded-2xl border border-dashed border-border bg-muted/30 p-3">
                <div className="flex items-center justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                      {t(lang, "scanner.barcodeCodeLabel")}
                    </p>
                    <p className="text-xs text-foreground font-mono tracking-wide break-all">{lastScannedBarcode}</p>
                  </div>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 shrink-0"
                    onClick={() => void copyBarcodeToClipboard(lastScannedBarcode)}
                    title={lang === "en" ? "Copy barcode" : "Kopyahin ang barcode"}
                  >
                    {barcodeCopied ? <CheckCircle2 className="w-4 h-4 text-success" /> : <Copy className="w-4 h-4" />}
                  </Button>
                </div>
              </div>
            )}

            {/* Low-confidence warning */}
            {scanResult.confidence < 70 && (
              <div className="flex items-start gap-2 p-3 rounded-xl bg-warning/10 text-warning text-xs">
                <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
                <p>
                  {lang === "en"
                    ? "Low confidence result — this medicine was not found in our verified Philippine database. Please verify the details on the physical package before use."
                    : "Mababang tiwala — hindi nahanap ang gamot na ito sa aming na-verify na Philippine database. Pakikumpirma ang detalye sa aktwal na pakete bago gamitin."}
                </p>
              </div>
            )}

            <div className="bg-card rounded-2xl border border-border p-6 space-y-5 shadow-sm">
              <div className="mb-2">
                <p className="text-2xl sm:text-3xl font-bold text-foreground leading-tight tracking-tight break-words">
                  {scanResult.name}
                </p>
                {scanResult.genericName && scanResult.genericName !== scanResult.name && (
                  <p className="text-sm text-muted-foreground mt-1.5 font-medium italic opacity-80">
                    {scanResult.genericName}
                  </p>
                )}
              </div>

              <div className="space-y-4">
                <div className="p-4 rounded-2xl bg-slate-50 border border-slate-100 dark:bg-slate-900/40 dark:border-slate-800">
                  <p className="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">
                    {t(lang, "scanner.dosageHeading")}
                  </p>
                  <p className="text-base text-foreground leading-relaxed font-medium">{scanResult.dosage || "—"}</p>
                </div>

                <div className="p-4 rounded-2xl bg-slate-50 border border-slate-100 dark:bg-slate-900/40 dark:border-slate-800">
                  <p className="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">
                    {lang === "en" ? "Typical Intake / Frequency" : "Dalas ng Pag-inom / Frequency"}
                  </p>
                  <p className="text-base text-foreground leading-relaxed font-medium">{scanResult.frequency || "—"}</p>
                </div>

                <div className="p-4 rounded-2xl bg-emerald-50/50 border border-emerald-100/50 dark:bg-emerald-950/20 dark:border-emerald-900/30">
                  <p className="text-[11px] font-bold text-emerald-600/80 dark:text-emerald-400/80 uppercase tracking-widest mb-2">
                    {lang === "en" ? "Purpose" : "Para saan / Purpose"}
                  </p>
                  <p className="text-base text-foreground leading-relaxed">
                    {lang === "en" ? scanResult.purpose || scanResult.purposeFil : scanResult.purposeFil || scanResult.purpose}
                  </p>
                </div>

                <div className="p-4 rounded-2xl bg-orange-50/50 border border-orange-100/50 dark:bg-orange-950/20 dark:border-orange-900/30">
                  <p className="text-[11px] font-bold text-orange-600/80 dark:text-orange-400/80 uppercase tracking-widest mb-2">
                    {lang === "en" ? "Warnings & Precautions" : "Mga Babala / Warnings"}
                  </p>
                  <p className="text-base text-foreground leading-relaxed">
                    {lang === "en"
                      ? scanResult.precautions || scanResult.precautionsFil
                      : scanResult.precautionsFil || scanResult.precautions}
                  </p>
                </div>
              </div>
            </div>

            <div className="flex gap-3 flex-wrap">
              <Button
                variant="outline"
                onClick={() => {
                  setScanResult(null);
                  setLastScannedBarcode(null);
                  setBarcodeCopied(false);
                }}
                className="flex-1 min-w-[140px] h-12 rounded-xl gap-2"
              >
                <RefreshCw className="w-4 h-4" />
                {t(lang, "scanner.scanAgain")}
              </Button>
              {allowSaveToList && (
                <Button onClick={handleAddFromScan} className="flex-1 min-w-[140px] h-12 rounded-xl">
                  {t(lang, "scanner.addToList")}
                </Button>
              )}
            </div>
            {!isAuthorizedToSave && (
              <p className="text-xs text-center text-muted-foreground mt-2">{t(lang, "scanner.listNotePatient")}</p>
            )}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}