import type { Locale } from "./i18n-config";

export const policySlugs = [
  "terms",
  "privacy",
  "refund",
  "complaints",
] as const;
export type PolicySlug = (typeof policySlugs)[number];

type PolicySection = {
  title: string;
  paragraphs?: string[];
  bullets?: string[];
};

export type Policy = {
  slug: PolicySlug;
  title: string;
  shortTitle: string;
  summary: string;
  updated: string;
  sections: PolicySection[];
};

export const policyUi = {
  vi: {
    eyebrow: "VIVE HOST · TRUNG TÂM PHÁP LÝ",
    title: "Chính sách minh bạch cho một nền tảng đáng tin cậy.",
    description:
      "Các điều khoản dưới đây giải thích quyền, trách nhiệm và cách Vive Host bảo vệ người dùng trong suốt vòng đời ứng dụng.",
    allPolicies: "Tất cả chính sách",
    updated: "Cập nhật",
    contact: "Liên hệ về chính sách",
    contactDescription: "Gửi câu hỏi, yêu cầu dữ liệu hoặc khiếu nại đến",
    backHome: "Về trang chủ",
    choose: "Chọn chính sách để xem chi tiết",
  },
  en: {
    eyebrow: "VIVE HOST · LEGAL CENTER",
    title: "Clear policies for a platform you can trust.",
    description:
      "These policies explain the rights, responsibilities, and safeguards that apply throughout your application lifecycle on Vive Host.",
    allPolicies: "All policies",
    updated: "Updated",
    contact: "Policy contact",
    contactDescription:
      "Send policy questions, data requests, or complaints to",
    backHome: "Back to home",
    choose: "Choose a policy to read the details",
  },
} satisfies Record<Locale, Record<string, string>>;

export const policies: Record<Locale, Record<PolicySlug, Policy>> = {
  vi: {
    terms: {
      slug: "terms",
      title: "Điều khoản sử dụng dịch vụ",
      shortTitle: "Điều khoản sử dụng",
      summary:
        "Quy định việc tạo tài khoản, triển khai ứng dụng, sử dụng tài nguyên và trách nhiệm của Vive Host lẫn người dùng.",
      updated: "01/09/2026",
      sections: [
        {
          title: "1. Phạm vi và chấp thuận",
          paragraphs: [
            "Điều khoản này là thỏa thuận giữa người dùng và Vive Host đối với website, API, dashboard, MCP, hạ tầng triển khai và các dịch vụ liên quan. Khi tạo tài khoản hoặc tiếp tục sử dụng dịch vụ, bạn xác nhận đã đọc và đồng ý với Điều khoản, Chính sách bảo mật cùng các chính sách được dẫn chiếu.",
            "Nếu bạn sử dụng Vive Host thay mặt tổ chức, bạn xác nhận mình có thẩm quyền ràng buộc tổ chức đó với các điều khoản này.",
          ],
        },
        {
          title: "2. Tài khoản và bảo mật",
          bullets: [
            "Cung cấp thông tin chính xác, cập nhật và sử dụng email mà bạn có quyền kiểm soát.",
            "Bảo vệ mật khẩu, token API, token MCP, khóa truy cập repository và mọi thông tin xác thực liên quan.",
            "Thông báo ngay cho Vive Host khi phát hiện truy cập trái phép hoặc sự cố bảo mật.",
            "Bạn chịu trách nhiệm đối với hoạt động phát sinh từ tài khoản của mình, trừ trường hợp do lỗi trực tiếp của Vive Host.",
          ],
        },
        {
          title: "3. Dịch vụ và giai đoạn Open Beta",
          paragraphs: [
            "Vive Host cung cấp công cụ build, deploy, domain nền tảng, HTTPS, logs, biến môi trường, database và các tính năng vận hành theo cấu hình hiển thị trên sản phẩm. Một số tính năng có thể phụ thuộc vào nhà cung cấp hạ tầng hoặc dịch vụ bên thứ ba.",
            "Trong Open Beta, dịch vụ được cung cấp để thử nghiệm thực tế, có thể thay đổi và chưa đi kèm cam kết SLA. Vive Host sẽ nỗ lực thông báo hợp lý trước các thay đổi quan trọng, trừ tình huống khẩn cấp về an toàn hoặc ổn định hệ thống.",
          ],
        },
        {
          title: "4. Sử dụng được phép",
          paragraphs: [
            "Bạn chỉ được sử dụng Vive Host cho mục đích hợp pháp và phải có quyền đối với mã nguồn, dữ liệu, domain và nội dung được triển khai.",
          ],
          bullets: [
            "Không phát tán mã độc, phishing, spam, nội dung lừa đảo hoặc nội dung vi phạm pháp luật.",
            "Không xâm phạm quyền sở hữu trí tuệ, quyền riêng tư hoặc bí mật kinh doanh của bên khác.",
            "Không tấn công, dò quét trái phép, né hạn mức, khai thác lỗ hổng hoặc làm gián đoạn hệ thống và người dùng khác.",
            "Không vận hành botnet, proxy công cộng, đào tiền mã hóa hoặc workload gây rủi ro bất thường nếu chưa được Vive Host chấp thuận bằng văn bản.",
            "Không tải lên secrets, dữ liệu cá nhân hoặc dữ liệu nhạy cảm nếu bạn không có cơ sở hợp pháp và biện pháp bảo vệ phù hợp.",
          ],
        },
        {
          title: "5. Mã nguồn, dữ liệu và quyền sở hữu",
          paragraphs: [
            "Bạn giữ quyền sở hữu đối với mã nguồn và dữ liệu của mình. Bạn cấp cho Vive Host quyền kỹ thuật giới hạn, không độc quyền để sao chép, build, lưu trữ, truyền và xử lý nội dung trong phạm vi cần thiết để cung cấp dịch vụ.",
            "Bạn có trách nhiệm duy trì bản sao độc lập của mã nguồn, database và dữ liệu quan trọng. Logs hoặc bản sao vận hành của hệ thống không thay thế cho chiến lược backup của bạn.",
          ],
        },
        {
          title: "6. Tài nguyên và sử dụng hợp lý",
          paragraphs: [
            "Mỗi tài khoản và ứng dụng chịu hạn mức CPU, RAM, disk, số ứng dụng và số build đồng thời được hiển thị trong dashboard. Vive Host có thể giới hạn hoặc tạm dừng workload vượt quota, gây suy giảm hệ thống hoặc ảnh hưởng người dùng khác.",
            "Không được chia nhỏ tài khoản, tự động tạo tài khoản hoặc dùng biện pháp kỹ thuật để né hạn mức.",
          ],
        },
        {
          title: "7. Tạm ngưng và chấm dứt",
          paragraphs: [
            "Vive Host có thể tạm ngưng hoặc chấm dứt tài khoản khi có vi phạm chính sách, yêu cầu hợp pháp, rủi ro bảo mật, hành vi gian lận, nợ thanh toán trong tương lai hoặc nguy cơ ảnh hưởng hạ tầng. Trong trường hợp không khẩn cấp, chúng tôi sẽ cố gắng gửi thông báo và tạo cơ hội khắc phục hợp lý.",
            "Bạn có thể ngừng sử dụng dịch vụ bất kỳ lúc nào. Trước khi chấm dứt, bạn cần tự xuất và lưu dữ liệu cần thiết.",
          ],
        },
        {
          title: "8. Phí và thanh toán",
          paragraphs: [
            "Open Beta hiện được cung cấp miễn phí theo quota công bố. Nếu Vive Host mở gói trả phí, giá, chu kỳ, thuế, gia hạn và điều kiện thanh toán sẽ được hiển thị trước khi bạn xác nhận mua.",
            "Không có khoản phí nào được thu chỉ dựa trên điều khoản này. Chính sách hoàn tiền áp dụng theo trạng thái thương mại hiện hành của dịch vụ.",
          ],
        },
        {
          title: "9. Tính sẵn sàng và giới hạn trách nhiệm",
          paragraphs: [
            "Vive Host áp dụng các biện pháp hợp lý để duy trì dịch vụ an toàn và ổn định nhưng không bảo đảm dịch vụ luôn không gián đoạn hoặc không có lỗi, đặc biệt trong Open Beta và với sự cố từ Internet, GitHub hoặc nhà cung cấp hạ tầng.",
            "Trong phạm vi pháp luật cho phép, mỗi bên chỉ chịu trách nhiệm đối với thiệt hại trực tiếp có thể dự đoán hợp lý. Không nội dung nào loại trừ trách nhiệm không thể loại trừ theo pháp luật Việt Nam.",
          ],
        },
        {
          title: "10. Thay đổi và giải quyết tranh chấp",
          paragraphs: [
            "Chính sách có thể được cập nhật để phản ánh thay đổi sản phẩm hoặc pháp luật. Phiên bản mới được công bố tại trang này kèm ngày cập nhật; thay đổi ảnh hưởng đáng kể sẽ được thông báo bằng kênh phù hợp.",
            "Các bên ưu tiên thương lượng thiện chí. Nếu không giải quyết được, tranh chấp được xử lý theo pháp luật Việt Nam tại cơ quan có thẩm quyền.",
          ],
        },
      ],
    },
    privacy: {
      slug: "privacy",
      title: "Chính sách bảo mật và dữ liệu cá nhân",
      shortTitle: "Chính sách bảo mật",
      summary:
        "Giải thích dữ liệu Vive Host thu thập, mục đích xử lý, thời gian lưu giữ và quyền kiểm soát của người dùng.",
      updated: "01/09/2026",
      sections: [
        {
          title: "1. Phạm vi",
          paragraphs: [
            "Chính sách này áp dụng khi bạn truy cập website, tạo tài khoản, sử dụng dashboard, API, MCP hoặc các dịch vụ triển khai của Vive Host. Vive Host là đơn vị quyết định mục đích và phương thức xử lý dữ liệu trong phạm vi sản phẩm.",
          ],
        },
        {
          title: "2. Dữ liệu chúng tôi thu thập",
          bullets: [
            "Thông tin tài khoản: họ tên, email, trạng thái xác minh, vai trò và mật khẩu đã băm.",
            "Thông tin triển khai: URL repository, branch, framework, domain, cấu hình tài nguyên và lịch sử deployment.",
            "Dữ liệu vận hành: build logs, runtime logs, số liệu sử dụng, mã lỗi, audit trail và thời điểm thao tác.",
            "Thông tin kỹ thuật: địa chỉ IP, user agent, cookie ngôn ngữ, token phiên và dữ liệu cần thiết để phát hiện lạm dụng.",
            "Nội dung bạn chủ động cung cấp khi yêu cầu hỗ trợ, khiếu nại hoặc sử dụng tính năng tích hợp.",
          ],
        },
        {
          title: "3. Secrets và thông tin xác thực",
          paragraphs: [
            "Biến môi trường được đánh dấu secret, thông tin database và token nhạy cảm được xử lý để cung cấp chức năng tương ứng và không được thiết kế để hiển thị lại sau khi tạo. Bạn không nên gửi secrets qua email hoặc kênh hỗ trợ không bảo mật.",
            "Bạn chịu trách nhiệm xác định dữ liệu nào được phép đưa lên Vive Host và xoay vòng credential khi nghi ngờ bị lộ.",
          ],
        },
        {
          title: "4. Mục đích xử lý",
          bullets: [
            "Tạo và bảo vệ tài khoản; xác thực yêu cầu và phân quyền.",
            "Build, deploy, vận hành, đo mức sử dụng và hỗ trợ ứng dụng của bạn.",
            "Gửi thông báo giao dịch, bảo mật, xác minh email và khôi phục tài khoản.",
            "Phòng chống gian lận, lạm dụng, tấn công và điều tra sự cố.",
            "Cải thiện độ tin cậy, trải nghiệm sản phẩm và tuân thủ nghĩa vụ pháp lý.",
          ],
        },
        {
          title: "5. Cookie và lưu trữ trên trình duyệt",
          paragraphs: [
            "Vive Host dùng cookie vive_locale trong tối đa 12 tháng để ghi nhớ ngôn ngữ. Token đăng nhập được lưu trên trình duyệt để duy trì phiên làm việc. Bạn có thể xóa cookie và dữ liệu website trong cài đặt trình duyệt, nhưng thao tác này có thể đăng xuất tài khoản hoặc đặt lại lựa chọn ngôn ngữ.",
          ],
        },
        {
          title: "6. Chia sẻ dữ liệu",
          paragraphs: [
            "Vive Host không bán dữ liệu cá nhân. Dữ liệu chỉ được chia sẻ trong phạm vi cần thiết với:",
          ],
          bullets: [
            "Nhà cung cấp hạ tầng, database, cache, email, giám sát và nền tảng triển khai hoạt động thay mặt Vive Host.",
            "Dịch vụ bạn chủ động kết nối, chẳng hạn GitHub hoặc domain riêng.",
            "Cố vấn chuyên môn hoặc bên tiếp nhận chuyển giao doanh nghiệp với nghĩa vụ bảo mật phù hợp.",
            "Cơ quan nhà nước hoặc bên có thẩm quyền khi pháp luật yêu cầu hoặc để bảo vệ quyền và an toàn hợp pháp.",
          ],
        },
        {
          title: "7. Lưu giữ và xóa dữ liệu",
          paragraphs: [
            "Dữ liệu được lưu trong thời gian cần thiết để cung cấp dịch vụ, bảo đảm an ninh, xử lý tranh chấp và tuân thủ pháp luật. Sau khi tài khoản hoặc tài nguyên bị xóa, một số bản ghi bảo mật, audit hoặc bản sao lưu có thể còn tồn tại trong chu kỳ lưu giữ giới hạn trước khi được xóa hoặc ẩn danh.",
            "Thời gian thực tế phụ thuộc loại dữ liệu, nghĩa vụ pháp lý và khả năng kỹ thuật của hệ thống lưu trữ.",
          ],
        },
        {
          title: "8. Quyền của bạn",
          bullets: [
            "Yêu cầu biết, truy cập hoặc nhận bản sao dữ liệu cá nhân liên quan đến mình.",
            "Yêu cầu sửa dữ liệu không chính xác, hạn chế xử lý, rút lại sự đồng ý hoặc xóa dữ liệu khi pháp luật cho phép.",
            "Phản đối hoạt động xử lý hoặc gửi khiếu nại về cách dữ liệu được sử dụng.",
          ],
          paragraphs: [
            "Vive Host có thể cần xác minh danh tính trước khi xử lý yêu cầu và có thể giữ lại dữ liệu nếu pháp luật hoặc lợi ích an ninh hợp pháp yêu cầu.",
          ],
        },
        {
          title: "9. An toàn và trẻ vị thành niên",
          paragraphs: [
            "Chúng tôi áp dụng phân quyền, mã hóa phù hợp, giới hạn tài nguyên, audit và các biện pháp kỹ thuật–tổ chức nhằm giảm rủi ro. Không hệ thống trực tuyến nào có thể bảo đảm an toàn tuyệt đối.",
            "Dịch vụ không được thiết kế cho người chưa đủ năng lực giao kết theo pháp luật. Nếu phát hiện dữ liệu trẻ em được cung cấp trái phép, hãy liên hệ để được xử lý.",
          ],
        },
        {
          title: "10. Liên hệ",
          paragraphs: [
            "Gửi yêu cầu về quyền dữ liệu hoặc sự cố bảo mật đến support@vive.host. Chúng tôi sẽ xác nhận yêu cầu và phản hồi trong thời hạn hợp lý theo tính chất vụ việc và quy định áp dụng.",
          ],
        },
      ],
    },
    refund: {
      slug: "refund",
      title: "Chính sách thanh toán và hoàn tiền",
      shortTitle: "Thanh toán & hoàn tiền",
      summary:
        "Làm rõ trạng thái miễn phí của Open Beta và nguyên tắc áp dụng khi Vive Host mở gói trả phí.",
      updated: "01/09/2026",
      sections: [
        {
          title: "1. Open Beta hiện tại",
          paragraphs: [
            "Vive Host Open Beta hiện được cung cấp miễn phí trong quota công bố. Vì Vive Host chưa thu phí cho quota này, không có khoản thanh toán để hoàn lại. Việc không sử dụng hết quota hoặc tự ngừng sử dụng không tạo ra số dư hay tín dụng quy đổi thành tiền.",
          ],
        },
        {
          title: "2. Khi gói trả phí được mở",
          paragraphs: [
            "Trước khi thu tiền, Vive Host sẽ hiển thị rõ giá, thuế, chu kỳ thanh toán, phạm vi tài nguyên, gia hạn và điều kiện hoàn tiền tại bước xác nhận mua. Bạn chỉ bị tính phí sau khi chủ động xác nhận giao dịch bằng phương thức được hỗ trợ.",
          ],
        },
        {
          title: "3. Giao dịch đủ điều kiện xem xét",
          bullets: [
            "Giao dịch bị thu trùng hoặc số tiền thu khác với đơn hàng đã xác nhận.",
            "Gói trả phí không được kích hoạt do lỗi trực tiếp của Vive Host và không thể khắc phục trong thời gian hợp lý.",
            "Trường hợp khác được nêu cụ thể tại đơn hàng, chương trình hoặc cam kết bằng văn bản của Vive Host.",
          ],
        },
        {
          title: "4. Trường hợp không hoàn tiền",
          bullets: [
            "Dịch vụ đã được sử dụng đúng mô tả nhưng người dùng thay đổi nhu cầu, trừ khi điều kiện đơn hàng cho phép.",
            "Tài khoản bị hạn chế do vi phạm pháp luật, Điều khoản sử dụng hoặc hành vi né hạn mức.",
            "Chi phí bên thứ ba đã phát sinh và được ghi rõ là không hoàn lại, chẳng hạn domain hoặc license mua hộ.",
            "Sự cố do mã nguồn, cấu hình, repository, domain hoặc hệ thống bên thứ ba nằm ngoài quyền kiểm soát hợp lý của Vive Host.",
          ],
        },
        {
          title: "5. Cách gửi yêu cầu",
          paragraphs: [
            "Gửi yêu cầu đến support@vive.host kèm email tài khoản, mã giao dịch, thời điểm, số tiền và mô tả vấn đề. Không gửi đầy đủ số thẻ, mật khẩu ngân hàng hoặc mã OTP. Vive Host có thể yêu cầu thêm chứng từ cần thiết để xác minh giao dịch.",
          ],
        },
        {
          title: "6. Xử lý và hoàn tiền",
          paragraphs: [
            "Yêu cầu hợp lệ được xác nhận trong vòng 02 ngày làm việc. Sau khi chấp thuận, Vive Host sẽ thực hiện hoàn về phương thức phù hợp; thời gian tiền về phụ thuộc ngân hàng, cổng thanh toán và có thể kéo dài hơn thời gian xử lý nội bộ. Mọi điều kiện cụ thể của gói trả phí tại thời điểm mua sẽ được ưu tiên nếu có lợi hơn cho người dùng.",
          ],
        },
      ],
    },
    complaints: {
      slug: "complaints",
      title: "Quy trình giải quyết khiếu nại",
      shortTitle: "Giải quyết khiếu nại",
      summary:
        "Kênh tiếp nhận, thông tin cần cung cấp, thời hạn xử lý và cách chuyển cấp khi người dùng chưa đồng ý.",
      updated: "01/09/2026",
      sections: [
        {
          title: "1. Nguyên tắc",
          paragraphs: [
            "Vive Host tiếp nhận phản ánh một cách thiện chí, bảo mật và không phân biệt đối xử. Việc xử lý dựa trên dữ liệu hệ thống, thỏa thuận với người dùng và pháp luật Việt Nam. Gửi khiếu nại không làm mất các quyền hợp pháp khác của bạn.",
          ],
        },
        {
          title: "2. Kênh tiếp nhận",
          bullets: [
            "Email: support@vive.host.",
            "Kênh hỗ trợ trong dashboard khi tính năng này được cung cấp.",
            "Đối với sự cố bảo mật khẩn cấp, ghi rõ [SECURITY] trong tiêu đề email để được ưu tiên phân loại.",
          ],
        },
        {
          title: "3. Thông tin cần cung cấp",
          bullets: [
            "Email tài khoản và tên ứng dụng hoặc mã tài nguyên liên quan.",
            "Nội dung sự việc, thời điểm, kết quả mong muốn và các bước đã thử.",
            "Logs, ảnh chụp hoặc chứng từ liên quan sau khi đã che mật khẩu, token và dữ liệu nhạy cảm không cần thiết.",
          ],
        },
        {
          title: "4. Trình tự xử lý",
          bullets: [
            "Bước 1 — Tiếp nhận: Vive Host xác nhận yêu cầu trong vòng 02 ngày làm việc và có thể đề nghị bổ sung thông tin.",
            "Bước 2 — Xác minh: đội ngũ liên quan kiểm tra log, lịch sử thao tác, cấu hình và ý kiến các bên.",
            "Bước 3 — Phản hồi: kết quả, biện pháp xử lý và lý do được gửi bằng văn bản qua kênh đã xác minh.",
            "Bước 4 — Xem xét lại: nếu chưa đồng ý, bạn có thể yêu cầu chuyển cấp và cung cấp căn cứ bổ sung.",
          ],
        },
        {
          title: "5. Thời hạn",
          paragraphs: [
            "Vive Host đặt mục tiêu giải quyết trong vòng 20 ngày kể từ khi nhận đủ thông tin. Vụ việc phức tạp, liên quan bên thứ ba hoặc cơ quan có thẩm quyền có thể cần thêm thời gian; khi đó chúng tôi sẽ thông báo tiến độ và lý do chậm trễ.",
          ],
        },
        {
          title: "6. Giải quyết tiếp theo",
          paragraphs: [
            "Nếu hai bên không đạt được thỏa thuận sau quá trình xem xét lại, mỗi bên có quyền gửi vụ việc đến cơ quan quản lý, cơ chế hòa giải hoặc Tòa án có thẩm quyền theo pháp luật Việt Nam.",
          ],
        },
      ],
    },
  },
  en: {
    terms: {
      slug: "terms",
      title: "Terms of Service",
      shortTitle: "Terms of Service",
      summary:
        "Rules for accounts, application deployments, resource usage, and the responsibilities of both Vive Host and its users.",
      updated: "September 1, 2026",
      sections: [
        {
          title: "1. Scope and acceptance",
          paragraphs: [
            "These Terms form an agreement between you and Vive Host for the website, API, dashboard, MCP, deployment infrastructure, and related services. By creating an account or continuing to use the service, you confirm that you have read and accepted these Terms, the Privacy Policy, and referenced policies.",
            "If you use Vive Host for an organization, you confirm that you are authorized to bind that organization to these Terms.",
          ],
        },
        {
          title: "2. Accounts and security",
          bullets: [
            "Provide accurate, current information and use an email address you control.",
            "Protect passwords, API tokens, MCP tokens, repository credentials, and other authentication material.",
            "Notify Vive Host promptly if you discover unauthorized access or a security incident.",
            "You are responsible for activity under your account except to the extent directly caused by Vive Host.",
          ],
        },
        {
          title: "3. Service and Open Beta",
          paragraphs: [
            "Vive Host provides build, deployment, platform domains, HTTPS, logs, environment variables, databases, and operational features as shown in the product. Some capabilities depend on infrastructure providers or third-party services.",
            "During Open Beta, the service is offered for real-world testing, may change, and does not include an SLA. We aim to provide reasonable notice of material changes unless urgent security or stability work is required.",
          ],
        },
        {
          title: "4. Acceptable use",
          paragraphs: [
            "You may use Vive Host only for lawful purposes and must have the necessary rights to all source code, data, domains, and content you deploy.",
          ],
          bullets: [
            "Do not distribute malware, phishing, spam, fraud, or unlawful content.",
            "Do not infringe intellectual property, privacy, or trade-secret rights.",
            "Do not attack, scan without authorization, evade limits, exploit vulnerabilities, or disrupt the platform or other users.",
            "Do not run botnets, public proxies, cryptocurrency mining, or unusually risky workloads without written approval.",
            "Do not upload personal, sensitive, or secret data without a lawful basis and suitable safeguards.",
          ],
        },
        {
          title: "5. Source code, data, and ownership",
          paragraphs: [
            "You retain ownership of your source code and data. You grant Vive Host a limited, non-exclusive technical license to copy, build, host, transmit, and process content only as needed to deliver the service.",
            "You must maintain independent copies of important source code, databases, and data. Operational logs or system copies are not a substitute for your backup strategy.",
          ],
        },
        {
          title: "6. Resources and fair use",
          paragraphs: [
            "Accounts and applications are subject to the CPU, memory, disk, application, and concurrent-build limits shown in the dashboard. Vive Host may limit or pause workloads that exceed quotas, degrade the platform, or affect other users.",
            "You may not split or automate account creation to bypass limits.",
          ],
        },
        {
          title: "7. Suspension and termination",
          paragraphs: [
            "Vive Host may suspend or terminate access for policy violations, lawful requests, security risks, fraud, future unpaid charges, or threats to infrastructure. Unless urgent, we will try to provide notice and a reasonable opportunity to remedy the issue.",
            "You may stop using the service at any time. Export and retain any data you need before termination.",
          ],
        },
        {
          title: "8. Fees and payment",
          paragraphs: [
            "Open Beta is currently free within the published quota. If paid plans launch, pricing, billing cycles, taxes, renewal, and payment terms will be shown before purchase.",
            "These Terms alone do not authorize a charge. The Refund Policy applies according to the service's current commercial status.",
          ],
        },
        {
          title: "9. Availability and liability",
          paragraphs: [
            "Vive Host uses reasonable measures to keep the service secure and stable but cannot promise uninterrupted or error-free operation, particularly during Open Beta or for incidents involving the Internet, GitHub, or infrastructure providers.",
            "To the extent permitted by law, each party is responsible only for reasonably foreseeable direct loss. Nothing excludes liability that cannot legally be excluded under Vietnamese law.",
          ],
        },
        {
          title: "10. Changes and disputes",
          paragraphs: [
            "Policies may be updated for product or legal changes. New versions are published here with an updated date, and material changes will be communicated through an appropriate channel.",
            "The parties will first seek a good-faith negotiated resolution. Unresolved disputes are governed by Vietnamese law and handled by a competent authority.",
          ],
        },
      ],
    },
    privacy: {
      slug: "privacy",
      title: "Privacy and Personal Data Policy",
      shortTitle: "Privacy Policy",
      summary:
        "What Vive Host collects, why it is processed, how long it is kept, and the controls available to users.",
      updated: "September 1, 2026",
      sections: [
        {
          title: "1. Scope",
          paragraphs: [
            "This Policy applies when you visit the website, create an account, or use the Vive Host dashboard, API, MCP, and deployment services. Vive Host determines the purposes and means of processing within the product.",
          ],
        },
        {
          title: "2. Data we collect",
          bullets: [
            "Account data: name, email, verification status, role, and hashed password.",
            "Deployment data: repository URL, branch, framework, domains, resource configuration, and deployment history.",
            "Operational data: build and runtime logs, usage metrics, errors, audit trails, and action timestamps.",
            "Technical data: IP address, user agent, language cookie, session tokens, and data required to detect abuse.",
            "Content you provide through support, complaints, or integrations.",
          ],
        },
        {
          title: "3. Secrets and credentials",
          paragraphs: [
            "Environment variables marked as secrets, database credentials, and sensitive tokens are processed to deliver their associated functionality and are not designed to be displayed again after creation. Do not send secrets through email or unsecured support channels.",
            "You are responsible for deciding what data may be uploaded and for rotating credentials if exposure is suspected.",
          ],
        },
        {
          title: "4. Why we process data",
          bullets: [
            "Create and protect accounts, authenticate requests, and enforce permissions.",
            "Build, deploy, operate, measure, and support your applications.",
            "Send transactional, security, email verification, and account recovery messages.",
            "Prevent fraud, abuse, attacks, and investigate incidents.",
            "Improve reliability and user experience and comply with legal duties.",
          ],
        },
        {
          title: "5. Cookies and browser storage",
          paragraphs: [
            "Vive Host uses the vive_locale cookie for up to 12 months to remember language. The sign-in token is stored in the browser to maintain your session. Clearing site data may sign you out or reset language preferences.",
          ],
        },
        {
          title: "6. Data sharing",
          paragraphs: [
            "Vive Host does not sell personal data. Data is shared only as needed with:",
          ],
          bullets: [
            "Infrastructure, database, cache, email, monitoring, and deployment providers acting for Vive Host.",
            "Services you choose to connect, such as GitHub or a custom domain.",
            "Professional advisers or a business transferee subject to suitable confidentiality obligations.",
            "Public authorities or authorized parties when legally required or necessary to protect lawful rights and safety.",
          ],
        },
        {
          title: "7. Retention and deletion",
          paragraphs: [
            "Data is retained as needed to provide the service, maintain security, resolve disputes, and comply with law. After account or resource deletion, certain security, audit, or backup records may remain for a limited retention cycle before deletion or anonymization.",
            "Actual periods depend on the data type, legal duties, and storage-system capabilities.",
          ],
        },
        {
          title: "8. Your rights",
          bullets: [
            "Request information, access, or a copy of personal data about you.",
            "Request correction, restriction, consent withdrawal, or deletion where legally available.",
            "Object to processing or complain about how data is used.",
          ],
          paragraphs: [
            "We may verify identity before fulfilling a request and may retain data where required by law or legitimate security needs.",
          ],
        },
        {
          title: "9. Security and minors",
          paragraphs: [
            "We use access controls, appropriate encryption, resource isolation, audit logging, and organizational safeguards to reduce risk. No online system can guarantee absolute security.",
            "The service is not designed for people who lack legal capacity to contract. Contact us if a child's data appears to have been submitted without authorization.",
          ],
        },
        {
          title: "10. Contact",
          paragraphs: [
            "Send data-rights requests or security concerns to support@vive.host. We will acknowledge and respond within a reasonable period based on the request and applicable requirements.",
          ],
        },
      ],
    },
    refund: {
      slug: "refund",
      title: "Payment and Refund Policy",
      shortTitle: "Payment & refunds",
      summary:
        "The free status of Open Beta and the principles that will apply when Vive Host launches paid plans.",
      updated: "September 1, 2026",
      sections: [
        {
          title: "1. Current Open Beta",
          paragraphs: [
            "Vive Host Open Beta is currently free within its published quota. Because no fee is charged for that quota, there is no payment to refund. Unused quota or voluntary discontinuation does not create cash credit.",
          ],
        },
        {
          title: "2. When paid plans launch",
          paragraphs: [
            "Before collecting payment, Vive Host will clearly show price, taxes, billing period, included resources, renewal, and refund conditions at confirmation. You will be charged only after actively confirming a transaction through a supported method.",
          ],
        },
        {
          title: "3. Transactions eligible for review",
          bullets: [
            "A duplicated charge or an amount that differs from the confirmed order.",
            "A paid plan that was not activated due directly to Vive Host and could not be remedied within a reasonable period.",
            "Another case expressly covered by the order, promotion, or a written Vive Host commitment.",
          ],
        },
        {
          title: "4. Non-refundable cases",
          bullets: [
            "The service worked as described but needs changed, unless the order terms say otherwise.",
            "The account was restricted for unlawful activity, a Terms violation, or limit evasion.",
            "Clearly disclosed non-refundable third-party costs, such as a purchased domain or license.",
            "Issues caused by source code, configuration, repository, domain, or a third party beyond Vive Host's reasonable control.",
          ],
        },
        {
          title: "5. Submitting a request",
          paragraphs: [
            "Email support@vive.host with the account email, transaction reference, date, amount, and issue. Never send a full card number, banking password, or OTP. Additional evidence may be required to verify the transaction.",
          ],
        },
        {
          title: "6. Review and payment",
          paragraphs: [
            "Valid requests are acknowledged within two business days. Once approved, a refund is initiated through a suitable method; receipt timing depends on banks and payment providers and may exceed Vive Host's internal processing time. More favorable terms shown for a paid plan at purchase will prevail.",
          ],
        },
      ],
    },
    complaints: {
      slug: "complaints",
      title: "Complaint Resolution Process",
      shortTitle: "Complaint resolution",
      summary:
        "Available channels, required information, response targets, and escalation when a user disagrees with an outcome.",
      updated: "September 1, 2026",
      sections: [
        {
          title: "1. Principles",
          paragraphs: [
            "Vive Host handles complaints in good faith, confidentially, and without discrimination. Decisions are based on system records, user agreements, and Vietnamese law. Making a complaint does not waive your other lawful rights.",
          ],
        },
        {
          title: "2. Contact channels",
          bullets: [
            "Email: support@vive.host.",
            "Dashboard support channels when available.",
            "For an urgent security incident, include [SECURITY] in the subject for priority triage.",
          ],
        },
        {
          title: "3. Information to include",
          bullets: [
            "Account email and the related application name or resource identifier.",
            "What happened, when it happened, the desired outcome, and troubleshooting already attempted.",
            "Relevant logs, screenshots, or records after redacting passwords, tokens, and unnecessary sensitive data.",
          ],
        },
        {
          title: "4. Resolution steps",
          bullets: [
            "Step 1 — Intake: Vive Host acknowledges the request within two business days and may request more information.",
            "Step 2 — Investigation: the relevant team checks logs, actions, configuration, and information from involved parties.",
            "Step 3 — Response: the outcome, remedy, and reasoning are sent in writing through the verified channel.",
            "Step 4 — Review: if you disagree, request escalation and provide any additional basis.",
          ],
        },
        {
          title: "5. Timeframe",
          paragraphs: [
            "Vive Host aims to resolve complaints within 20 days after receiving sufficient information. Complex cases involving third parties or authorities may take longer; we will communicate progress and the reason for delay.",
          ],
        },
        {
          title: "6. Further resolution",
          paragraphs: [
            "If no agreement is reached after review, either party may submit the matter to a regulator, mediation mechanism, or competent court under Vietnamese law.",
          ],
        },
      ],
    },
  },
};

export function isPolicySlug(value: string): value is PolicySlug {
  return policySlugs.includes(value as PolicySlug);
}
