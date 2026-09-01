"use client";

import Link from "next/link";
import { LanguageSwitcher } from "@/components/language-switcher";
import { useI18n } from "@/lib/i18n";

const frameworks = ["Next.js", "Laravel", "Node.js", "Python", "Static"];

export default function Home() {
  const { t, href } = useI18n();
  return (
    <main className="landing">
      <nav className="landing-nav">
        <Link className="brand landing-brand" href={href("/")}>
          <span>V</span>
          <strong>Vive Host</strong>
          <small>Open Beta</small>
        </Link>
        <div className="landing-links">
          <a href="#product">{t("Sản phẩm")}</a>
          <a href="#workflow">{t("Cách hoạt động")}</a>
          <a href="#security">{t("Bảo mật")}</a>
        </div>
        <div className="landing-nav-actions">
          <LanguageSwitcher compact />
          <Link className="nav-login" href={href("/dashboard")}>
            {t("Đăng nhập")}
          </Link>
          <Link
            className="button primary"
            href={`${href("/dashboard")}?mode=register`}
          >
            {t("Deploy miễn phí")} <span aria-hidden="true">→</span>
          </Link>
        </div>
      </nav>

      <section className="hero">
        <div className="hero-copy">
          <div className="beta-label">
            <i /> {t("OPEN BETA · HẠ TẦNG ĐANG SẴN SÀNG")}
          </div>
          <h1>
            {t("Từ GitHub đến")}
            <br />
            <em>Internet.</em> {t("Nhanh gọn.")}
          </h1>
          <p>
            {t(
              "Deploy ứng dụng AI và web app trong vài phút. Vive tự lo build, HTTPS, database, logs và vận hành — bạn chỉ cần tập trung vào sản phẩm.",
            )}
          </p>
          <div className="hero-actions">
            <Link
              className="button primary large"
              href={`${href("/dashboard")}?mode=register`}
            >
              {t("Deploy ứng dụng đầu tiên")} <span aria-hidden="true">↗</span>
            </Link>
            <a className="button ghost large" href="#product">
              <span className="play-icon" aria-hidden="true">
                ▶
              </span>{" "}
              {t("Xem sản phẩm")}
            </a>
          </div>
          <div className="hero-trust">
            <span>
              <b>✓</b> {t("Không cần thẻ")}
            </span>
            <span>
              <b>✓</b> {t("HTTPS tự động")}
            </span>
            <span>
              <b>✓</b> {t("Khởi tạo trong vài phút")}
            </span>
          </div>
        </div>
        <ProductPreview />
      </section>

      <section
        className="framework-strip"
        aria-label={t("Framework được hỗ trợ")}
      >
        <span>{t("SHIP WITH")}</span>
        {frameworks.map((framework) => (
          <b key={framework}>{framework}</b>
        ))}
      </section>

      <section id="product" className="landing-section feature-section">
        <div className="section-kicker">{t("MỌI THỨ BẠN CẦN ĐỂ SHIP")}</div>
        <div className="section-heading">
          <h2>
            {t("Hạ tầng mạnh mẽ.")}
            <br />
            {t("Trải nghiệm đơn giản.")}
          </h2>
          <p>
            {t(
              "Một control plane gọn gàng cho toàn bộ vòng đời ứng dụng — từ commit đầu tiên đến lúc vận hành ổn định.",
            )}
          </p>
        </div>
        <div className="feature-grid">
          <article className="feature-card feature-card-wide">
            <div className="feature-icon purple">⌘</div>
            <h3>{t("Deploy từ GitHub")}</h3>
            <p>
              {t(
                "Dán repository, chọn branch và framework. Vive tự phát hiện cấu hình, build cô lập và đưa ứng dụng lên Internet.",
              )}
            </p>
            <div className="git-flow">
              <span className="git-node">GH</span>
              <i />
              <span className="git-node vive-node">V</span>
              <i />
              <span className="git-node live-node">✓</span>
              <small>github.com/you/app</small>
              <small>{t("Build & deploy")}</small>
              <small>{t("Live HTTPS")}</small>
            </div>
          </article>
          <article className="feature-card">
            <div className="feature-icon green">↗</div>
            <h3>{t("HTTPS & domain")}</h3>
            <p>
              {t(
                "Platform domain sẵn có, TLS tự động và hỗ trợ custom domain khi bạn cần.",
              )}
            </p>
            <div className="domain-chip">
              <i /> app.vive.host <b>SSL</b>
            </div>
          </article>
          <article className="feature-card">
            <div className="feature-icon blue">▤</div>
            <h3>{t("Logs realtime")}</h3>
            <p>
              {t(
                "Theo dõi build và runtime logs ngay trong dashboard, không cần SSH vào server.",
              )}
            </p>
            <pre className="mini-terminal">
              <span>$ deploy --production</span>
              {"\n"}
              <b>✓ Build completed</b>
              {"\n"}
              <b>✓ Service is live</b>
            </pre>
          </article>
          <article className="feature-card">
            <div className="feature-icon amber">◫</div>
            <h3>{t("Managed PostgreSQL")}</h3>
            <p>
              {t(
                "Tạo database riêng, credentials mã hóa và tự động đồng bộ environment variables.",
              )}
            </p>
            <div className="database-visual">
              <span>PG</span>
              <div>
                <b>PostgreSQL 17</b>
                <small>{t("Healthy · Encrypted")}</small>
              </div>
              <i />
            </div>
          </article>
          <article className="feature-card feature-card-wide">
            <div className="feature-icon rose">◉</div>
            <h3>{t("Quan sát và kiểm soát")}</h3>
            <p>
              {t(
                "Usage, deployment history, restart, stop và recovery trong cùng một nơi.",
              )}
            </p>
            <div className="metric-preview">
              <div>
                <span>CPU</span>
                <b>12%</b>
                <i>
                  <em style={{ width: "12%" }} />
                </i>
              </div>
              <div>
                <span>{t("Memory")}</span>
                <b>184 MB</b>
                <i>
                  <em style={{ width: "36%" }} />
                </i>
              </div>
              <div>
                <span>{t("Disk")}</span>
                <b>420 MB</b>
                <i>
                  <em style={{ width: "21%" }} />
                </i>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section id="workflow" className="landing-section workflow-section">
        <div className="workflow-copy">
          <div className="section-kicker">{t("TỪ Ý TƯỞNG ĐẾN PRODUCTION")}</div>
          <h2>
            {t("Ba bước.")}
            <br />
            {t("Một URL đang chạy.")}
          </h2>
          <p>
            {t(
              "Không YAML phức tạp, không reverse proxy, không mất cả buổi cấu hình VPS.",
            )}
          </p>
          <Link
            className="text-link"
            href={`${href("/dashboard")}?mode=register`}
          >
            {t("Bắt đầu deploy")} <span>→</span>
          </Link>
        </div>
        <div className="workflow">
          <article>
            <b>01</b>
            <div>
              <h3>{t("Kết nối mã nguồn")}</h3>
              <p>{t("Dán GitHub URL, chọn branch và framework phù hợp.")}</p>
            </div>
            <span>github.com/you/app</span>
          </article>
          <article>
            <b>02</b>
            <div>
              <h3>{t("Vive build cô lập")}</h3>
              <p>{t("Mỗi workload có giới hạn CPU, RAM và disk riêng.")}</p>
            </div>
            <span className="build-pulse">
              <i /> {t("Building")}
            </span>
          </article>
          <article>
            <b>03</b>
            <div>
              <h3>{t("Nhận HTTPS URL")}</h3>
              <p>
                {t("Theo dõi trạng thái, logs và tài nguyên trong dashboard.")}
              </p>
            </div>
            <span className="live-url">
              <i /> app.vive.host
            </span>
          </article>
        </div>
      </section>

      <section id="security" className="landing-section security-section">
        <div>
          <div className="section-kicker">{t("SECURITY BY DEFAULT")}</div>
          <h2>{t("Ship nhanh mà không đánh đổi an toàn.")}</h2>
          <p>
            {t(
              "Workload được cô lập, secrets được mã hóa và mọi thao tác quan trọng đều có audit trail.",
            )}
          </p>
        </div>
        <div className="security-list">
          <article>
            <span>01</span>
            <div>
              <b>{t("Workload isolation")}</b>
              <small>
                {t(
                  "Không privileged, không Docker socket, giới hạn tài nguyên rõ ràng.",
                )}
              </small>
            </div>
          </article>
          <article>
            <span>02</span>
            <div>
              <b>{t("Encrypted secrets")}</b>
              <small>
                {t(
                  "Biến môi trường và database credentials không hiển thị lại.",
                )}
              </small>
            </div>
          </article>
          <article>
            <span>03</span>
            <div>
              <b>{t("Append-only audit logs")}</b>
              <small>
                {t("Lịch sử thao tác không thể chỉnh sửa hoặc xóa.")}
              </small>
            </div>
          </article>
          <article>
            <span>04</span>
            <div>
              <b>{t("Scoped MCP access")}</b>
              <small>
                {t("Token giới hạn quyền và có thể thu hồi tức thì.")}
              </small>
            </div>
          </article>
        </div>
      </section>

      <section className="landing-cta">
        <div className="cta-orb" />
        <div>
          <span>{t("READY TO SHIP?")}</span>
          <h2>
            {t("Đưa ứng dụng tiếp theo")}
            <br />
            {t("của bạn lên Internet.")}
          </h2>
          <p>{t("Open Beta miễn phí. Bắt đầu với một repository GitHub.")}</p>
        </div>
        <Link
          className="button cta-button large"
          href={`${href("/dashboard")}?mode=register`}
        >
          {t("Deploy ngay")} <span>↗</span>
        </Link>
      </section>
      <footer className="landing-footer">
        <Link className="brand" href={href("/")}>
          <span>V</span> Vive Host
        </Link>
        <p>{t("Hạ tầng deploy đơn giản dành cho builder Việt Nam.")}</p>
        <nav className="footer-policies" aria-label={t("Chính sách")}>
          <Link href={href("/policies/terms")}>{t("Điều khoản sử dụng")}</Link>
          <Link href={href("/policies/privacy")}>
            {t("Chính sách bảo mật")}
          </Link>
          <Link href={href("/policies/refund")}>
            {t("Thanh toán & hoàn tiền")}
          </Link>
          <Link href={href("/policies/complaints")}>
            {t("Giải quyết khiếu nại")}
          </Link>
        </nav>
        <div className="footer-locale">
          <LanguageSwitcher compact />
          <small>© 2026 Vive Host · Open Beta</small>
        </div>
      </footer>
    </main>
  );
}

function ProductPreview() {
  const { t } = useI18n();
  return (
    <div
      className="product-preview"
      aria-label={t("Xem trước Vive Host dashboard")}
    >
      <div className="preview-glow" />
      <div className="preview-window">
        <header>
          <div className="window-dots">
            <i />
            <i />
            <i />
          </div>
          <span>console.vive.host</span>
          <b>•••</b>
        </header>
        <div className="preview-app">
          <aside>
            <div className="preview-logo">V</div>
            <nav>
              <i className="active" />
              <i />
              <i />
              <i />
            </nav>
            <span />
          </aside>
          <div className="preview-main">
            <div className="preview-top">
              <span>{t("Workspace / Overview")}</span>
              <b>
                <i /> {t("Operational")}
              </b>
            </div>
            <div className="preview-title">
              <div>
                <small>{t("APPLICATION")}</small>
                <h3>atlas-ai</h3>
              </div>
              <button>{t("+ Deploy")}</button>
            </div>
            <div className="preview-stats">
              <div>
                <span>{t("Status")}</span>
                <b className="preview-running">
                  <i /> {t("Running")}
                </b>
              </div>
              <div>
                <span>{t("Deployments")}</span>
                <b>24</b>
              </div>
              <div>
                <span>{t("Uptime")}</span>
                <b>99.99%</b>
              </div>
            </div>
            <div className="preview-deploy">
              <div className="deploy-head">
                <div>
                  <span className="app-avatar">A</span>
                  <p>
                    <b>{t("Production deployment")}</b>
                    <small>main · a8f19c2</small>
                  </p>
                </div>
                <strong>
                  <i /> {t("Live")}
                </strong>
              </div>
              <div className="deploy-steps">
                <span className="done">
                  <i>✓</i>
                  {t("Source")}
                </span>
                <em />
                <span className="done">
                  <i>✓</i>
                  {t("Build")}
                </span>
                <em />
                <span className="done">
                  <i>✓</i>
                  {t("Deploy")}
                </span>
              </div>
              <div className="preview-url">
                <i /> https://atlas-ai.vive.host <b>↗</b>
              </div>
            </div>
            <div className="preview-chart">
              <div>
                <span>{t("Resource usage")}</span>
                <b>{t("Last 24 hours")}</b>
              </div>
              <svg
                viewBox="0 0 500 100"
                preserveAspectRatio="none"
                aria-hidden="true"
              >
                <defs>
                  <linearGradient id="chart-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#7c6cf2" stopOpacity=".3" />
                    <stop offset="1" stopColor="#7c6cf2" stopOpacity="0" />
                  </linearGradient>
                </defs>
                <path
                  d="M0 75 C35 70 50 82 80 62 S125 68 155 45 S210 65 245 43 S300 48 330 27 S385 48 420 23 S465 35 500 14 L500 100 L0 100Z"
                  fill="url(#chart-fill)"
                />
                <path
                  d="M0 75 C35 70 50 82 80 62 S125 68 155 45 S210 65 245 43 S300 48 330 27 S385 48 420 23 S465 35 500 14"
                  fill="none"
                  stroke="#7c6cf2"
                  strokeWidth="2"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>
      <div className="floating-card build-card">
        <i>✓</i>
        <div>
          <b>{t("Deployment complete")}</b>
          <small>atlas-ai · 42s</small>
        </div>
      </div>
      <div className="floating-card ssl-card">
        <span>⌁</span>
        <div>
          <b>{t("HTTPS secured")}</b>
          <small>{t("Certificate active")}</small>
        </div>
      </div>
    </div>
  );
}
