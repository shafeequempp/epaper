/*
 * EpaperExport.jsx — Mathrubhumi ePaper export for InDesign (CC 2019+ / 2024)
 *
 * Put this file in the Scripts Panel folder and run it from Window > Utilities > Scripts.
 *
 * File names tell the script where each page goes:
 *     9pg2e-LocalNews.indd   ->  day 9, page 2, edition code E (Kothamangalam-Muvattupuzha)
 *     16pg1cc-Front.indd     ->  day 16, page 1, edition code CC (Ernakulam)
 * The month and year come from the date chosen in the dialog.
 * A folder may hold several editions; each page goes to its own edition.
 *
 * Two ways to run it:
 *   Current document            exports the open page
 *   All .indd files in a folder  opens every .indd without showing it, exports it,
 *                               closes it without saving
 *
 * Output, in a folder named _epaper_<date> next to the files:
 *   <name>_p<N>.jpg   the page exactly as designed (default 200 dpi)
 *   manifest.json     all pages with edition code, date and articles
 *   export_log.txt    what was found on each page, and the upload results
 *
 * With "Upload to the ePaper dashboard" ticked it sends each page to api/import.php,
 * which stores images and articles in the database and can publish each edition.
 * Set uploadUrl and apiKey below first (apiKey = 'api_key' in the dashboard's config.php).
 *
 * Article detection, in order of accuracy:
 *   1. InDesign Articles panel (Window > Articles)
 *   2. Stories, with the largest group around each story (works with groups inside groups)
 *   3. Grouped frames and threaded stories with nearby headlines, photos and captions
 *   4. Every text frame on the page
 * Linked files inside a folder named "ads" are treated as ads and skipped.
 */
#target indesign

(function () {
    var CONFIG = {
        resolution: 200,          // page JPEG dpi (200 is sharp enough for zoom; 150 = smaller files)
        minChars: 60,             // text shorter than this is not an article on its own
        headlineMinPt: 16,        // a short frame with type this big is treated as a headline
        headlineMaxChars: 250,
        joinGap: 24,              // pt: how close a headline/photo/caption must be to join a story
        adPathPattern: /[\\\/]ads[\\\/]/i,

        // File name: <day>pg<page><edition code>-<anything>.indd, e.g. 9pg2e-LocalNews.indd
        fileNamePattern: /^(\d{1,2})pg(\d+)([a-z][a-z0-9]*)/i,

        // ---- upload to the dashboard
        // WAMP:       "http://localhost/epaper/api/import.php"
        // Production: "https://<your-server>/epaper/api/import.php"
        uploadUrl: "http://localhost/epaper/api/import.php",
        apiKey: "",               // must match 'api_key' in config.php; empty = upload switched off
        curl: "curl.exe"          // built into Windows 10/11; on a Mac "curl" is used
    };

    var doc = null;
    var logLines = [];
    function log(line) { logLines.push(line); }

    var opts = showDialog();
    if (!opts) return;
    run(opts);

    // ================================================================ dialog

    function showDialog() {
        var hasDoc = app.documents.length > 0;
        var w = new Window("dialog", "Mathrubhumi ePaper export");
        w.alignChildren = "fill";

        var src = w.add("panel", undefined, "Pages to export");
        src.alignChildren = "left";
        src.margins = 14;
        var rDoc = src.add("radiobutton", undefined, "Current document" + (hasDoc ? ": " + app.activeDocument.name : " (none open)"));
        var rFolder = src.add("radiobutton", undefined, "All .indd files in a folder");
        var fRow = src.add("group");
        var fBtn = fRow.add("button", undefined, "Choose folder...");
        var fPath = fRow.add("statictext", undefined, "No folder chosen", { truncate: "middle" });
        fPath.preferredSize.width = 330;
        src.add("statictext", undefined, "File names like 9pg2e-LocalNews.indd give the day (9), page (2) and edition (E).");

        var iss = w.add("panel", undefined, "Issue date");
        iss.alignChildren = "left";
        iss.margins = 14;
        var dRow = iss.add("group");
        dRow.add("statictext", undefined, "Date (YYYY-MM-DD):");
        var dText = dRow.add("edittext", undefined, tomorrow());
        dText.characters = 12;
        iss.add("statictext", undefined, "Month and year are taken from here; the day comes from each file name.");

        var aft = w.add("panel", undefined, "After export");
        aft.alignChildren = "left";
        aft.margins = 14;
        var cUpload = aft.add("checkbox", undefined, "Upload to the ePaper dashboard and save to the database");
        var cPublish = aft.add("checkbox", undefined, "Publish each edition when all its pages uploaded");
        var canUpload = CONFIG.apiKey !== "" && CONFIG.uploadUrl !== "";
        cUpload.value = canUpload;
        cUpload.enabled = canUpload;
        cPublish.enabled = canUpload;
        if (!canUpload) aft.add("statictext", undefined, "To enable upload, set uploadUrl and apiKey at the top of this script.");
        cUpload.onClick = function () {
            cPublish.enabled = cUpload.value;
            if (!cUpload.value) cPublish.value = false;
        };

        var btns = w.add("group");
        btns.alignment = "right";
        btns.add("button", undefined, "Cancel", { name: "cancel" });
        var okBtn = btns.add("button", undefined, "Export", { name: "ok" });

        var folder = null;
        rDoc.enabled = hasDoc;
        rDoc.value = hasDoc;
        rFolder.value = !hasDoc;
        if (hasDoc) {
            try { guessDate(app.activeDocument.filePath.fsName); } catch (e) {}
        }

        fBtn.onClick = function () {
            var f = Folder.selectDialog("Choose the folder with the .indd pages");
            if (f) {
                folder = f;
                fPath.text = f.fsName;
                rFolder.value = true;
                rDoc.value = false;
                guessDate(f.fsName);
            }
        };

        // a date written as YYYYMMDD in the folder path, e.g. ...\20261009\...
        function guessDate(path) {
            var m = /(20\d{2})(\d{2})(\d{2})/.exec(path);
            if (m) dText.text = m[1] + "-" + m[2] + "-" + m[3];
        }

        okBtn.onClick = function () {
            if (rFolder.value && !folder) { alert("Choose the folder with the .indd files."); return; }
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dText.text)) { alert("Write the date like 2026-10-09."); return; }
            w.close(1);
        };

        if (w.show() !== 1) return null;
        return {
            mode: rFolder.value ? "folder" : "doc",
            folder: folder,
            date: dText.text,
            upload: cUpload.value,
            publish: cPublish.value
        };
    }

    // ================================================================ run

    function run(opts) {
        var items = [], skipped = [], outRoot;   // items: { file?, name, info }

        if (opts.mode === "folder") {
            var files = opts.folder.getFiles(function (f) {
                return f instanceof File && /\.indd$/i.test(f.name) && !/^[~.]/.test(f.name);
            });
            if (!files.length) { alert("There are no .indd files in\n" + opts.folder.fsName); return; }
            for (var i = 0; i < files.length; i++) {
                var nm = decodeURI(files[i].name), info = parseName(nm);
                if (info) items.push({ file: files[i], name: nm, info: info });
                else skipped.push(nm);
            }
            items.sort(function (a, b) {
                if (a.info.code !== b.info.code) return a.info.code < b.info.code ? -1 : 1;
                if (a.info.day !== b.info.day) return a.info.day - b.info.day;
                return a.info.page - b.info.page;
            });
            outRoot = opts.folder;
        } else {
            var dn = app.activeDocument.name, di = parseName(dn);
            if (!di) {
                alert("The file name \"" + dn + "\" does not show day, page and edition.\n" +
                      "Save it with a name like 9pg2e-LocalNews.indd and run the script again.");
                return;
            }
            items.push({ file: null, name: dn, info: di });
            try { outRoot = app.activeDocument.filePath; } catch (e) { outRoot = null; }
            if (!outRoot) outRoot = Folder.selectDialog("Choose where to save the ePaper export");
            if (!outRoot) return;
        }
        if (!items.length) {
            alert("None of the .indd file names look like 9pg2e-LocalNews.indd (day, pg, page, edition code).\n\nSkipped: " +
                  skipped.slice(0, 10).join(", "));
            return;
        }

        var outDir = new Folder(outRoot.fsName + "/_epaper_" + opts.date);
        if (!outDir.exists) outDir.create();
        var upDir = new Folder(outDir.fsName + "/_upload");
        if (!upDir.exists) upDir.create();
        clearFolder(outDir, /\.(jpe?g|json)$/i);
        clearFolder(upDir, /\.(json|txt|bat)$/i);

        logLines = ["EpaperExport log, InDesign " + app.version + ", " + isoNow(),
                    "Date " + opts.date + ", " + items.length + " file(s)" + (opts.folder ? " from " + opts.folder.fsName : "")];
        for (var s = 0; s < skipped.length; s++) log("SKIPPED " + skipped[s] + ": file name is not like 9pg2e-Name.indd");

        var combined = {
            generator: "EpaperExport 3.0", issue_date_base: opts.date, indesign_version: app.version,
            exported_at: isoNow(), missing_links: [], pages: []
        };
        var perPage = [], failed = [], failedKeys = {}, emptyPages = [], seen = {}, totalArticles = 0;
        var codes = [];

        var bar = progress(items.length + (opts.upload ? 1 : 0));
        var oldUI = app.scriptPreferences.userInteractionLevel;
        if (opts.mode === "folder") app.scriptPreferences.userInteractionLevel = UserInteractionLevels.NEVER_INTERACT;

        try {
            for (var k = 0; k < items.length; k++) {
                var it = items[k];
                var issueDate = dateForDay(it.info.day, opts.date);
                var key = it.info.code + "|" + issueDate;
                if (indexOf(codes, it.info.code) < 0) codes.push(it.info.code);
                bar.step(k, "Exporting " + (k + 1) + " of " + items.length + ": " + it.name);
                var openedHere = false;
                try {
                    if (it.file) {
                        doc = alreadyOpen(it.file);
                        if (!doc) { doc = app.open(it.file, false); openedHere = true; }
                    } else {
                        doc = app.activeDocument;
                        if (!confirmMissingLinks()) return;
                    }
                    log("");
                    log("== " + it.name + ": edition " + it.info.code + ", page " + it.info.page + ", " + issueDate);
                    var res = exportDocument(outDir, it.info, issueDate);
                    for (var m = 0; m < res.missing.length; m++) combined.missing_links.push(it.name + ": " + res.missing[m]);

                    for (var p = 0; p < res.pages.length; p++) {
                        var pg = res.pages[p];
                        var pk = key + "|" + pg.page_number;
                        if (seen[pk]) log("WARNING " + it.name + " and " + seen[pk] + " are both page " + pg.page_number +
                                          " of edition " + it.info.code + "; the later upload replaces the earlier one");
                        seen[pk] = it.name;
                        totalArticles += pg.articles.length;
                        if (!pg.articles.length) emptyPages.push(it.name);
                        combined.pages.push(pg);

                        var single = {
                            generator: combined.generator, source: it.name, indesign_version: app.version,
                            exported_at: combined.exported_at, missing_links: res.missing, pages: [pg]
                        };
                        var mfile = new File(upDir.fsName + "/" + pg.image.replace(/\.jpg$/i, "") + ".json");
                        writeText(mfile, toJSON(single));
                        perPage.push({ key: key, code: it.info.code, date: issueDate, number: pg.page_number,
                                       manifest: mfile, image: new File(outDir.fsName + "/" + pg.image), source: it.name });
                    }
                } catch (err) {
                    failed.push(it.name);
                    failedKeys[key] = true;
                    log("ERROR in " + it.name + ": " + err.message + (err.line ? " (script line " + err.line + ")" : ""));
                } finally {
                    if (openedHere && doc) {
                        try { doc.close(SaveOptions.NO); } catch (e2) {}
                    }
                }
            }
        } finally {
            app.scriptPreferences.userInteractionLevel = oldUI;
        }

        writeText(new File(outDir.fsName + "/manifest.json"), toJSON(combined));

        var upload = null;
        if (opts.upload && perPage.length) {
            bar.step(items.length, "Uploading " + perPage.length + " page(s) to the dashboard...");
            upload = uploadAll(perPage, codes, opts, upDir, failedKeys, bar);
        }
        bar.close();
        writeText(new File(outDir.fsName + "/export_log.txt"), logLines.join("\n"));

        // ---------- summary
        var msg = "Exported " + combined.pages.length + " page(s) with " + totalArticles + " article(s).\nSaved in " + outDir.fsName;
        if (skipped.length) msg += "\n\nSKIPPED " + skipped.length + " file(s) with names not like 9pg2e-Name.indd:\n" + skipped.slice(0, 6).join("\n");
        if (failed.length) msg += "\n\nCOULD NOT EXPORT " + failed.length + " file(s):\n" + failed.slice(0, 6).join("\n");
        if (emptyPages.length) msg += "\n\nNO ARTICLES FOUND in: " + emptyPages.slice(0, 6).join(", ");
        if (combined.missing_links.length) msg += "\n\n" + combined.missing_links.length + " missing linked image(s); those pages use low-resolution previews.";
        if (upload) {
            msg += "\n\nUploaded " + upload.ok + " of " + perPage.length + " page(s), " + upload.articles + " article(s) saved.";
            for (var e = 0; e < upload.editions.length && e < 12; e++) msg += "\n  " + upload.editions[e];
            if (upload.errors.length) msg += "\n\nUpload problems:\n" + upload.errors.slice(0, 6).join("\n");
        } else if (!opts.upload) {
            msg += "\n\nUpload manifest.json and the JPG files on the dashboard (Upload pages).";
        }
        msg += "\n\nDetails: export_log.txt";
        alert(msg);
        outDir.execute();
    }

    // ================================================================ file names and dates

    function parseName(name) {
        var m = CONFIG.fileNamePattern.exec(name);
        if (!m) return null;
        var day = parseInt(m[1], 10);
        if (day < 1 || day > 31) return null;
        return { day: day, page: parseInt(m[2], 10), code: m[3].toUpperCase() };
    }

    // The date with this day of the month that is closest to the chosen date
    // (so day 30 with a chosen date of 1 November means 30 October).
    function dateForDay(day, base) {
        var b = base.split("-"), y = +b[0], mo = +b[1] - 1, d0 = new Date(y, mo, +b[2]);
        var best = null, bestDiff = 1e15;
        for (var off = -1; off <= 1; off++) {
            var c = new Date(y, mo + off, day);
            if (c.getDate() !== day) continue;            // e.g. 31 in a 30-day month
            var diff = Math.abs(c.getTime() - d0.getTime());
            if (diff < bestDiff) { bestDiff = diff; best = c; }
        }
        if (!best) best = d0;
        function z(n) { return (n < 10 ? "0" : "") + n; }
        return best.getFullYear() + "-" + z(best.getMonth() + 1) + "-" + z(best.getDate());
    }

    // ================================================================ one document

    function exportDocument(outDir, info, issueDate) {
        var base = doc.name.replace(/\.indd$/i, "").replace(/[^A-Za-z0-9_\-]+/g, "_");
        var missing = missingLinks();
        if (missing.length) log("Missing links: " + missing.join(", "));

        var oldUnit = app.scriptPreferences.measurementUnit;
        var oldOrigin = doc.viewPreferences.rulerOrigin;
        var oldZero = doc.zeroPoint;
        app.scriptPreferences.measurementUnit = MeasurementUnits.POINTS;
        doc.viewPreferences.rulerOrigin = RulerOrigin.SPREAD_ORIGIN;
        doc.zeroPoint = [0, 0];

        var pages = [];
        try {
            for (var p = 0; p < doc.pages.length; p++) {
                var page = doc.pages[p];
                var pb = page.bounds; // [y1, x1, y2, x2]
                var ctx = { page: page, pb: pb, pw: pb[3] - pb[1], ph: pb[2] - pb[0] };
                // single-page file: page number from the file name; multi-page file: counts on from it
                var number = info.page + p;

                var articles = [];
                if (doc.articles.length > 0) {
                    articles = fromArticlesPanel(ctx);
                    log("Page " + number + ": Articles panel has " + doc.articles.length + " article(s), " + articles.length + " on this page");
                }
                if (articles.length === 0) articles = detectByStories(ctx);
                if (articles.length === 0) articles = autoDetect(ctx);
                if (articles.length === 0) articles = fallbackAllText(ctx);

                var out = [];
                for (var a = 0; a < articles.length; a++) {
                    var built = buildArticle(articles[a], ctx);
                    if (built) { built.ref = "a" + (out.length + 1); out.push(built); }
                }
                log("Page " + number + ": " + out.length + " article(s)");

                var imgName = base + "_p" + (page.documentOffset + 1) + ".jpg";
                exportPageJpeg(page, new File(outDir.fsName + "/" + imgName));

                pages.push({
                    index: page.documentOffset,
                    edition_code: info.code,
                    issue_date: issueDate,
                    page_number: number,
                    name: String(number),
                    source: doc.name,
                    image: imgName,
                    width_pt: round(ctx.pw, 2),
                    height_pt: round(ctx.ph, 2),
                    resolution: CONFIG.resolution,
                    articles: out
                });
            }
        } finally {
            app.scriptPreferences.measurementUnit = oldUnit;
            try {
                doc.viewPreferences.rulerOrigin = oldOrigin;
                doc.zeroPoint = oldZero;
            } catch (e) {}
        }
        return { pages: pages, missing: missing };
    }

    function missingLinks() {
        var missing = [];
        for (var li = 0; li < doc.links.length; li++) {
            try {
                if (doc.links[li].status == LinkStatus.LINK_MISSING) missing.push(doc.links[li].name);
            } catch (e) {}
        }
        return missing;
    }

    function confirmMissingLinks() {
        var missing = missingLinks();
        if (!missing.length) return true;
        return confirm(missing.length + " linked file(s) are missing, e.g.\n" + missing.slice(0, 5).join("\n") +
            "\n\nMissing images export at low resolution. Continue anyway?");
    }

    function alreadyOpen(file) {
        for (var i = 0; i < app.documents.length; i++) {
            try { if (app.documents[i].fullName.fsName === file.fsName) return app.documents[i]; } catch (e) {}
        }
        return null;
    }

    // ================================================================ upload

    function uploadAll(perPage, codes, opts, upDir, failedKeys, bar) {
        var res = { ok: 0, articles: 0, errors: [], editions: [] };
        var byKey = {}, order = [];
        log("");
        log("== Upload to " + CONFIG.uploadUrl);
        for (var i = 0; i < perPage.length; i++) {
            var it = perPage[i];
            if (!byKey[it.key]) { byKey[it.key] = { code: it.code, date: it.date, ok: 0, total: 0, name: "" }; order.push(it.key); }
            var ed = byKey[it.key];
            ed.total++;
            bar.text("Uploading " + it.code + " page " + it.number + " (" + (i + 1) + " of " + perPage.length + ")");
            var r = post([["action", "import"], ["edition_code", it.code], ["issue_date", it.date], ["batch_codes", codes.join(",")]],
                         [["manifest", it.manifest, "application/json"], ["files[]", it.image, "image/jpeg"]],
                         upDir, "up_" + i);
            if (r.ok) {
                res.ok++;
                ed.ok++;
                var n = /"articles"\s*:\s*(\d+)/.exec(r.body);
                var en = /"edition_name"\s*:\s*"([^"]*)"/.exec(r.body);
                if (en) ed.name = en[1];
                if (n) res.articles += parseInt(n[1], 10);
                log(it.code + " page " + it.number + " (" + it.date + "): uploaded" + (en ? " to " + en[1] : "") + (n ? ", " + n[1] + " article(s)" : ""));
            } else {
                res.errors.push(it.source + ": " + r.error);
                log(it.code + " page " + it.number + ": UPLOAD FAILED: " + r.error);
            }
        }
        for (var k = 0; k < order.length; k++) {
            var e = byKey[order[k]];
            var line = e.code + (e.name ? " " + e.name : "") + ", " + e.date + ": " + e.ok + " of " + e.total + " page(s)";
            if (opts.publish) {
                if (e.ok < e.total || failedKeys[order[k]]) {
                    line += ", NOT published (some pages failed)";
                } else {
                    var p = post([["action", "publish"], ["edition_code", e.code], ["issue_date", e.date], ["batch_codes", codes.join(",")]],
                                 [], upDir, "publish_" + k);
                    line += p.ok ? ", published" : ", NOT published: " + p.error;
                }
            }
            res.editions.push(line);
            log(line);
        }
        return res;
    }

    /* POSTs a form with curl and returns { ok, body, error }.
       Windows: runs a hidden .bat through VBScript. Mac: do shell script. */
    function post(fields, files, dir, tag) {
        var respFile = new File(dir.fsName + "/" + tag + ".response.json");
        var codeFile = new File(dir.fsName + "/" + tag + ".status.txt");
        var errFile = new File(dir.fsName + "/" + tag + ".curl-error.txt");
        if (respFile.exists) respFile.remove();
        if (codeFile.exists) codeFile.remove();
        var all = [["api_key", CONFIG.apiKey]].concat(fields);
        var win = File.fs === "Windows";

        if (win) {
            var line = q(CONFIG.curl) + " -s -S --max-time 600 -o " + q(respFile.fsName) + ' -w "%%{http_code}"';
            for (var i = 0; i < all.length; i++) line += " -F " + q(all[i][0] + "=" + String(all[i][1]).replace(/%/g, "%%"));
            for (var j = 0; j < files.length; j++) line += " -F " + q(files[j][0] + "=@" + files[j][1].fsName.replace(/%/g, "%%") + ";type=" + files[j][2]);
            line += " " + q(CONFIG.uploadUrl) + " > " + q(codeFile.fsName) + " 2> " + q(errFile.fsName);
            var bat = new File(dir.fsName + "/" + tag + ".bat");
            writeText(bat, "rem ePaper upload\n@echo off\nchcp 65001 >nul\n" + line + "\n", "\r\n");
            app.doScript('Set sh = CreateObject("WScript.Shell")\r\nsh.Run """' + bat.fsName + '""", 0, True', ScriptLanguage.VISUAL_BASIC);
        } else {
            var cmd = "curl -s -S --max-time 600 -o " + sq(respFile.fsName) + " -w '%{http_code}'";
            for (var k = 0; k < all.length; k++) cmd += " -F " + sq(all[k][0] + "=" + all[k][1]);
            for (var f = 0; f < files.length; f++) cmd += " -F " + sq(files[f][0] + "=@" + files[f][1].fsName + ";type=" + files[f][2]);
            cmd += " " + sq(CONFIG.uploadUrl) + " > " + sq(codeFile.fsName) + " 2> " + sq(errFile.fsName);
            app.doScript('do shell script "' + cmd.replace(/\\/g, "\\\\").replace(/"/g, '\\"') + '"', ScriptLanguage.APPLESCRIPT_LANGUAGE);
        }

        var code = readText(codeFile).replace(/\s+/g, "");
        var body = readText(respFile);
        if (!code || code === "000") {
            var ce = readText(errFile).replace(/\s+$/, "");
            return { ok: false, body: body, error: "could not reach the dashboard" + (ce ? " (" + ce + ")" : "") +
                (/not recognized|not found/i.test(ce) ? ". curl is missing on this PC." : "") };
        }
        if (code === "200" && /"ok"\s*:\s*true/.test(body)) return { ok: true, body: body, error: "" };
        var em = /"error"\s*:\s*"((?:[^"\\]|\\.)*)"/.exec(body);
        return { ok: false, body: body, error: "HTTP " + code + (em ? ": " + em[1].replace(/\\"/g, '"') : body ? ": " + body.substring(0, 160) : "") };
    }

    function q(s) { return '"' + String(s).replace(/"/g, "") + '"'; }
    function sq(s) { return "'" + String(s).replace(/'/g, "'\\''") + "'"; }

    // ================================================================ small helpers

    function indexOf(arr, v) {
        for (var i = 0; i < arr.length; i++) if (arr[i] === v) return i;
        return -1;
    }

    function progress(total) {
        var w = new Window("palette", "ePaper export");
        w.alignChildren = "fill";
        var t = w.add("statictext", undefined, "Starting...");
        t.preferredSize.width = 460;
        var b = w.add("progressbar", undefined, 0, Math.max(1, total));
        b.preferredSize.width = 460;
        w.show();
        return {
            step: function (i, text) { b.value = i; t.text = text; try { w.update(); } catch (e) {} },
            text: function (text) { t.text = text; try { w.update(); } catch (e) {} },
            close: function () { try { w.close(); } catch (e) {} }
        };
    }

    function writeText(file, text, lineFeed) {
        file.encoding = "UTF-8";
        file.lineFeed = lineFeed === "\r\n" ? "Windows" : "Unix";
        file.open("w");
        file.write(text);
        file.close();
    }

    function readText(file) {
        if (!file.exists) return "";
        file.encoding = "UTF-8";
        file.open("r");
        var s = file.read();
        file.close();
        return s;
    }

    function clearFolder(folder, pattern) {
        var list = folder.getFiles();
        for (var i = 0; i < list.length; i++) {
            if (list[i] instanceof File && pattern.test(list[i].name)) {
                try { list[i].remove(); } catch (e) {}
            }
        }
    }

    function tomorrow() {
        var d = new Date(new Date().getTime() + 86400000);
        function z(n) { return (n < 10 ? "0" : "") + n; }
        return d.getFullYear() + "-" + z(d.getMonth() + 1) + "-" + z(d.getDate());
    }

    // ---------------------------------------------------------------- detection

    function fromArticlesPanel(ctx) {
        var result = [];
        for (var i = 0; i < doc.articles.length; i++) {
            var members = doc.articles[i].articleMembers;
            var cand = { frames: [], bounds: null, kind: "article" };
            for (var m = 0; m < members.length; m++) {
                var it = members[m].itemRef;
                if (!onPage(it, ctx.page) || isAd(it)) continue;
                cand.bounds = union(cand.bounds, it.visibleBounds);
                addFrames(cand.frames, it);
            }
            if (cand.bounds) result.push(cand);
        }
        return result;
    }

    /*
     * Story-first detection, works with groups nested to any depth.
     * 1. Read every story in the document and keep the text frames that sit on this page.
     * 2. A "body" story has enough text and is not a big short headline.
     * 3. For each body story climb up through its parent groups and take the largest group
     *    that still holds only this one body story: that group is the article
     *    (headline, text, photos, captions). Without such a group the story is used alone,
     *    with a headline directly above it joined in.
     */
    function detectByStories(ctx) {
        var infos = [], bodyIds = {}, nStories = doc.stories.length;
        for (var i = 0; i < nStories; i++) {
            var st = doc.stories[i], frames = [];
            var tc = st.textContainers;
            for (var t = 0; t < tc.length; t++) {
                var fr = resolve(tc[t]);
                if (typeName(fr) === "TextFrame" && onPage(fr, ctx.page)) frames.push(fr);
            }
            if (!frames.length) continue;
            var len = textLength(frames), pt = maxPointSize(frames);
            var isHead = len <= CONFIG.headlineMaxChars && pt >= CONFIG.headlineMinPt;
            var info = { id: st.id, frames: frames, len: len, pt: pt, head: isHead, body: !isHead && len >= CONFIG.minChars };
            infos.push(info);
            if (info.body) bodyIds[st.id] = true;
        }
        var bodies = 0;
        for (var b = 0; b < infos.length; b++) if (infos[b].body) bodies++;
        log("Page " + ctx.page.name + " stories: " + nStories + " in document, " + infos.length +
            " on this page, " + bodies + " look like article text");

        var result = [], usedGroups = {}, loose = [], heads = [];
        for (var k = 0; k < infos.length; k++) {
            var inf = infos[k];
            if (!inf.body) { if (inf.head) heads.push(inf); continue; }
            var g = articleGroup(inf.frames[0], bodyIds);
            if (g && !usedGroups[g.id]) {
                usedGroups[g.id] = true;
                var frames2 = [];
                addFrames(frames2, g);
                frames2 = orderFrames(frames2, inf.id);
                result.push({ frames: frames2.main, captions: frames2.captions, bounds: g.visibleBounds, kind: "group" });
            } else if (!g) {
                var bb = null;
                for (var f = 0; f < inf.frames.length; f++) bb = union(bb, inf.frames[f].visibleBounds);
                var cand = { frames: inf.frames, bounds: bb, kind: "story" };
                loose.push(cand);
                result.push(cand);
            }
        }
        // join ungrouped headlines to the story right below them
        for (var h = 0; h < heads.length; h++) {
            if (inGroup(heads[h].frames[0])) continue;
            var hb = null;
            for (var q = 0; q < heads[h].frames.length; q++) hb = union(hb, heads[h].frames[q].visibleBounds);
            var target = nearest(hb, loose, "below");
            if (target) {
                target.frames = heads[h].frames.concat(target.frames);
                target.bounds = union(target.bounds, hb);
            }
        }
        log("  story detection found " + result.length + " article(s), " + loose.length + " without a group");
        return result;
    }

    // Largest enclosing group that contains exactly one body story
    function articleGroup(frame, bodyIds) {
        var best = null, node = frame;
        for (var depth = 0; depth < 30; depth++) {
            var parent;
            try { parent = resolve(node.parent); } catch (e) { break; }
            if (!parent || typeName(parent) !== "Group") break;
            if (bodyStoriesIn(parent, bodyIds) > 1) break;
            best = parent;
            node = parent;
        }
        return best;
    }

    function bodyStoriesIn(group, bodyIds) {
        var frames = [], seen = {}, n = 0;
        addFrames(frames, group);
        for (var i = 0; i < frames.length; i++) {
            var id;
            try { id = frames[i].parentStory.id; } catch (e) { continue; }
            if (bodyIds[id] && !seen[id]) { seen[id] = true; n++; }
        }
        return n;
    }

    function inGroup(frame) {
        try { return typeName(resolve(frame.parent)) === "Group"; } catch (e) { return false; }
    }

    // Big type first (headline), then the body story in thread order, small texts as captions
    function orderFrames(frames, bodyId) {
        var heads = [], body = [], other = [], caps = [];
        for (var i = 0; i < frames.length; i++) {
            var fr = frames[i], sid;
            try { sid = fr.parentStory.id; } catch (e) { sid = -1; }
            if (sid === bodyId) { body.push(fr); continue; }
            var len = frameText(fr).length, pt = maxPointSize([fr]);
            if (pt >= CONFIG.headlineMinPt && len <= CONFIG.headlineMaxChars) heads.push(fr);
            else if (len < CONFIG.minChars) caps.push(fr);
            else other.push(fr);
        }
        heads.sort(function (a, b) { return a.visibleBounds[0] - b.visibleBounds[0]; });
        try {
            var tc = body.length ? body[0].parentStory.textContainers : [], ord = {};
            for (var t = 0; t < tc.length; t++) ord[tc[t].id] = t;
            body.sort(function (a, b) { return (ord[a.id] || 0) - (ord[b.id] || 0); });
        } catch (e2) {}
        return { main: heads.concat(body).concat(other), captions: caps };
    }

    // InDesign often returns generic PageItem objects; getElements() gives the real type
    function resolve(o) {
        try {
            var e = o.getElements();
            if (e && e.length) return e[0];
        } catch (x) {}
        return o;
    }

    function typeName(o) {
        try { return o.constructor.name; } catch (e) {}
        try { return o.reflect.name; } catch (e2) {}
        return "";
    }

    function autoDetect(ctx) {
        var items = flatten(ctx.page.pageItems.everyItem().getElements());
        var cands = [], storyCands = {}, storyOrder = [], graphics = [], smallTexts = [];
        var counts = {};

        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            it = resolve(it);
            var type = typeName(it);
            counts[type] = (counts[type] || 0) + 1;
            if (!isVisible(it)) continue;

            if (type === "Group") {
                // a group holding one story = one article (headline + text + photo + caption)
                var gf = [];
                addFrames(gf, it);
                if (textLength(gf) >= CONFIG.minChars) {
                    cands.push({ frames: gf, bounds: it.visibleBounds, kind: "group" });
                } else if (isAd(it)) {
                    counts.ads = (counts.ads || 0) + 1;
                } else if (hasGraphics(it)) {
                    graphics.push({ bounds: it.visibleBounds, frames: gf });
                }
            } else if (type === "TextFrame") {
                var sid = it.parentStory.id;
                if (!storyCands[sid]) {
                    storyCands[sid] = { frames: [], bounds: null, kind: "story", story: it.parentStory };
                    storyOrder.push(sid);
                }
                storyCands[sid].frames.push(it);
                storyCands[sid].bounds = union(storyCands[sid].bounds, it.visibleBounds);
            } else if (isAd(it)) {
                counts.ads = (counts.ads || 0) + 1;
            } else if (hasGraphics(it)) {
                graphics.push({ bounds: it.visibleBounds, frames: [] });
            }
        }
        var c = [];
        for (var k in counts) if (counts.hasOwnProperty(k)) c.push(k + "=" + counts[k]);
        log("Page " + ctx.page.name + ": items " + c.join(", ") + "; stories " + storyOrder.length + "; groups as articles " + cands.length);

        // classify stories: body, headline or small text (captions, folios)
        var headlines = [];
        for (var s = 0; s < storyOrder.length; s++) {
            var c = storyCands[storyOrder[s]];
            sortByThread(c);
            var len = textLength(c.frames);
            var pt = maxPointSize(c.frames);
            log("  story " + storyOrder[s] + ": " + len + " chars, max " + pt + "pt, starts: " +
                frameText(c.frames[0]).substring(0, 40).replace(/[\r\n]+/g, " "));
            if (len <= CONFIG.headlineMaxChars && pt >= CONFIG.headlineMinPt) headlines.push(c);
            else if (len >= CONFIG.minChars) cands.push(c);
            else if (len > 0) smallTexts.push(c);
        }

        // headline joins the story directly below (or overlapping) it
        for (var h = 0; h < headlines.length; h++) {
            var target = nearest(headlines[h].bounds, cands, "below");
            if (target) {
                target.frames = headlines[h].frames.concat(target.frames);
                target.bounds = union(target.bounds, headlines[h].bounds);
            } else if (textLength(headlines[h].frames) >= CONFIG.minChars / 2) {
                cands.push(headlines[h]);
            }
        }
        // photos and captions join the nearest story
        var extras = graphics.concat(smallTexts);
        for (var g = 0; g < extras.length; g++) {
            var t = nearest(extras[g].bounds, cands, "any");
            if (t) {
                t.bounds = union(t.bounds, extras[g].bounds);
                t.captions = (t.captions || []).concat(extras[g].frames);
            }
        }
        return cands;
    }

    function nearest(b, cands, mode) {
        var best = null, bestGap = CONFIG.joinGap + 0.001;
        for (var i = 0; i < cands.length; i++) {
            var c = cands[i].bounds;
            var hOverlap = overlap(b[1], b[3], c[1], c[3]) / Math.max(1, b[3] - b[1]);
            var vOverlap = overlap(b[0], b[2], c[0], c[2]) / Math.max(1, b[2] - b[0]);
            var gap;
            if (mode === "below") {
                if (hOverlap < 0.5) continue;
                gap = Math.abs(c[0] - b[2]);                       // story top vs headline bottom
                if (b[0] >= c[0] && b[2] <= c[2]) gap = 0;          // headline inside the story box
            } else {
                if (hOverlap >= 0.6) gap = Math.max(0, Math.max(c[0] - b[2], b[0] - c[2]));
                else if (vOverlap >= 0.6) gap = Math.max(0, Math.max(c[1] - b[3], b[1] - c[3]));
                else continue;
            }
            if (gap < bestGap) { bestGap = gap; best = cands[i]; }
        }
        return best;
    }

    // ---------------------------------------------------------------- text

    function buildArticle(cand, ctx) {
        var frames = cand.frames;
        var headline = "", headPt = 0, parts = [];
        for (var f = 0; f < frames.length; f++) {
            var fr = frames[f];
            var txt = frameText(fr);
            if (!txt) continue;
            parts.push(txt);
            try {
                var paras = fr.paragraphs;
                for (var p = 0; p < paras.length; p++) {
                    var size = pointSize(paras[p]);
                    var pc = String(paras[p].contents).replace(/[\r\n]+$/, "");
                    if (size > headPt && pc.length > 2 && pc.length <= CONFIG.headlineMaxChars) {
                        headPt = size; headline = pc;
                    }
                }
            } catch (e) {}
        }
        var body = parts.join("\r");
        if (headline) {
            var at = body.indexOf(headline);
            if (at >= 0 && at < 400) body = body.substring(0, at) + body.substring(at + headline.length);
        }
        var captions = [];
        if (cand.captions) for (var c = 0; c < cand.captions.length; c++) {
            var ct = frameText(cand.captions[c]);
            if (ct) captions.push(clean(ct));
        }
        body = clean(body);
        headline = clean(headline).replace(/\n+/g, " ");
        if (!headline && !body) return null;

        return {
            method: cand.kind,
            headline: headline,
            body: body,
            captions: captions,
            headline_pt: round(headPt, 1),
            regions: [toRegion(cand.bounds, ctx)]
        };
    }

    function frameText(fr) {
        try { return String(fr.texts[0].contents); } catch (e) { return ""; }
    }

    function clean(s) {
        return String(s)
            .replace(/\r/g, "\n")
            .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u00AD\uFEFF]/g, "")
            .replace(/[ \t]+\n/g, "\n")
            .replace(/\n{3,}/g, "\n\n")
            .replace(/^\s+|\s+$/g, "");
    }

    function pointSize(para) {
        var s = para.pointSize;
        if (typeof s === "number") return s;
        try { return para.characters[0].pointSize; } catch (e) { return 0; }
    }

    function maxPointSize(frames) {
        var m = 0;
        for (var f = 0; f < frames.length; f++) {
            try {
                var ps = frames[f].paragraphs;
                for (var p = 0; p < ps.length; p++) m = Math.max(m, pointSize(ps[p]));
            } catch (e) {}
        }
        return m;
    }

    function textLength(frames) {
        var n = 0;
        for (var i = 0; i < frames.length; i++) n += frameText(frames[i]).length;
        return n;
    }

    function addFrames(list, item) {
        item = resolve(item);
        if (typeName(item) === "TextFrame") { list.push(item); return; }
        try {
            var all = item.allPageItems;
            for (var i = 0; i < all.length; i++) {
                var r = resolve(all[i]);
                if (typeName(r) === "TextFrame") list.push(r);
            }
        } catch (e) {}
    }

    function sortByThread(cand) {
        var tc = cand.story.textContainers, order = {};
        for (var i = 0; i < tc.length; i++) order[tc[i].id] = i;
        cand.frames.sort(function (a, b) { return (order[a.id] || 0) - (order[b.id] || 0); });
    }

    // ---------------------------------------------------------------- geometry

    function toRegion(b, ctx) {
        var x = (b[1] - ctx.pb[1]) / ctx.pw, y = (b[0] - ctx.pb[0]) / ctx.ph;
        var w = (b[3] - b[1]) / ctx.pw, h = (b[2] - b[0]) / ctx.ph;
        x = clamp(x); y = clamp(y);
        w = Math.min(w, 1 - x); h = Math.min(h, 1 - y);
        return { x: round(x, 5), y: round(y, 5), w: round(w, 5), h: round(h, 5) };
    }
    function union(a, b) {
        if (!a) return [b[0], b[1], b[2], b[3]];
        return [Math.min(a[0], b[0]), Math.min(a[1], b[1]), Math.max(a[2], b[2]), Math.max(a[3], b[3])];
    }
    function overlap(a1, a2, b1, b2) { return Math.max(0, Math.min(a2, b2) - Math.max(a1, b1)); }
    function clamp(v) { return Math.max(0, Math.min(1, v)); }
    function round(v, d) { var k = Math.pow(10, d); return Math.round(v * k) / k; }

    function onPage(item, page) {
        try { if (item.parentPage && item.parentPage.id === page.id) return true; } catch (e) {}
        try {
            var b = item.visibleBounds, pb = page.bounds;
            var cx = (b[1] + b[3]) / 2, cy = (b[0] + b[2]) / 2;
            return item.parentPage === null ? false : (cx >= pb[1] && cx <= pb[3] && cy >= pb[0] && cy <= pb[2]);
        } catch (e2) { return false; }
    }

    function hasGraphics(item) {
        try { return item.allGraphics.length > 0; } catch (e) { return false; }
    }

    /*
     * Last resort: every text frame on the spread that overlaps this page, at any depth
     * (inside groups, on hidden or locked layers, attached to the spread instead of the page).
     * One story = one article. Also logs what the page is made of, to explain an empty result.
     */
    function fallbackAllText(ctx) {
        var all = [];
        try { all = ctx.page.parent.allPageItems; } catch (e) { all = ctx.page.allPageItems; }
        var stories = {}, order = [], types = {}, totalChars = 0, frames = 0, onPage = 0;

        for (var i = 0; i < all.length; i++) {
            var it = resolve(all[i]), type = typeName(it);
            var b;
            try { b = it.visibleBounds; } catch (e) { continue; }
            if (overlap(b[1], b[3], ctx.pb[1], ctx.pb[3]) <= 0 || overlap(b[0], b[2], ctx.pb[0], ctx.pb[2]) <= 0) continue;
            onPage++;
            types[type] = (types[type] || 0) + 1;
            if (type !== "TextFrame") continue;
            frames++;
            var txt = frameText(it);
            totalChars += txt.length;
            var sid;
            try { sid = it.parentStory.id; } catch (e2) { sid = "f" + it.id; }
            if (!stories[sid]) { stories[sid] = { frames: [], bounds: null, kind: "fallback" }; order.push(sid); }
            stories[sid].frames.push(it);
            stories[sid].bounds = union(stories[sid].bounds, b);
        }

        var t = [];
        for (var k in types) if (types.hasOwnProperty(k)) t.push(k + "=" + types[k]);
        log("Page " + ctx.page.name + " fallback: " + onPage + " items overlap the page (" + t.join(", ") + "); " +
            frames + " text frames, " + totalChars + " characters of live text");
        if (frames === 0) {
            log("  No live text frames. The page may be a placed PDF/image or text converted to outlines; " +
                "articles then need OCR or the original stories.");
        }

        var result = [];
        for (var s = 0; s < order.length; s++) {
            var c = stories[order[s]];
            if (textLength(c.frames) >= CONFIG.minChars) result.push(c);
        }
        log("  fallback found " + result.length + " article(s)");
        return result;
    }

    // Opens groups that hold more than one story (e.g. a whole page or a section grouped together)
    function flatten(items) {
        var out = [];
        for (var i = 0; i < items.length; i++) {
            var it = resolve(items[i]);
            if (typeName(it) === "Group" && storiesIn(it) > 1) {
                out = out.concat(flatten(it.pageItems.everyItem().getElements()));
            } else {
                out.push(it);
            }
        }
        return out;
    }

    function storiesIn(group) {
        var seen = {}, n = 0, frames = [];
        addFrames(frames, group);
        for (var i = 0; i < frames.length; i++) {
            var id = frames[i].parentStory.id;
            if (!seen[id] && frameText(frames[i]).length >= CONFIG.minChars) { seen[id] = true; n++; }
        }
        return n;
    }

    function isVisible(item) {
        try { return item.visible !== false && item.itemLayer.visible; } catch (e) { return true; }
    }

    function isAd(item) {
        try {
            var gs = item.allGraphics;
            for (var i = 0; i < gs.length; i++) {
                if (gs[i].itemLink && CONFIG.adPathPattern.test(gs[i].itemLink.filePath)) return true;
            }
        } catch (e) {}
        return false;
    }

    // ---------------------------------------------------------------- export

    function exportPageJpeg(page, file) {
        var pr = app.jpegExportPreferences;
        pr.jpegExportRange = ExportRangeOrAllPages.EXPORT_RANGE;
        pr.pageString = "+" + (page.documentOffset + 1);   // absolute page number
        pr.exportingSpread = false;
        pr.exportResolution = CONFIG.resolution;
        pr.jpegQuality = JPEGOptionsQuality.HIGH;
        pr.jpegRenderingStyle = JPEGOptionsFormat.BASELINE_ENCODING;
        pr.jpegColorSpace = JpegColorSpaceEnum.RGB;
        pr.antiAlias = true;
        pr.useDocumentBleeds = false;
        pr.simulateOverprint = true;
        doc.exportFile(ExportFormat.JPG, file, false);
    }

    // ---------------------------------------------------------------- utils (ExtendScript has no JSON)

    function isoNow() {
        var d = new Date();
        function z(n) { return (n < 10 ? "0" : "") + n; }
        return d.getFullYear() + "-" + z(d.getMonth() + 1) + "-" + z(d.getDate()) + "T" +
            z(d.getHours()) + ":" + z(d.getMinutes()) + ":" + z(d.getSeconds());
    }

    function toJSON(v, ind) {
        ind = ind || "";
        var next = ind + "  ";
        if (v === null || v === undefined) return "null";
        if (typeof v === "number") return isFinite(v) ? String(v) : "null";
        if (typeof v === "boolean") return v ? "true" : "false";
        if (typeof v === "string") return quote(v);
        if (v instanceof Array) {
            if (!v.length) return "[]";
            var a = [];
            for (var i = 0; i < v.length; i++) a.push(next + toJSON(v[i], next));
            return "[\n" + a.join(",\n") + "\n" + ind + "]";
        }
        var o = [];
        for (var k in v) if (v.hasOwnProperty(k)) o.push(next + quote(k) + ": " + toJSON(v[k], next));
        return "{\n" + o.join(",\n") + "\n" + ind + "}";
    }

    function quote(s) {
        s = String(s);
        var out = "";
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i), code = s.charCodeAt(i);
            if (c === '"') out += '\\"';
            else if (c === "\\") out += "\\\\";
            else if (c === "\n") out += "\\n";
            else if (c === "\r") out += "\\n";
            else if (c === "\t") out += "\\t";
            else if (code < 32) out += "\\u" + ("000" + code.toString(16)).slice(-4);
            else out += c;
        }
        return '"' + out + '"';
    }
})();
